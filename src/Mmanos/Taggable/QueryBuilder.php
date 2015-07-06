<?php namespace Mmanos\Taggable;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class QueryBuilder extends Builder
{
	protected $model;
	protected $tag_context;
	protected $filters = array();
	protected $relations = array();
	protected $tag_filters_applied;
	
	/**
	 * Set the model for this instance.
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model
	 * 
	 * @return QueryBuilder
	 */
	public function setModel($model)
	{
		$this->model = $model;
		
		$this->from($this->model->taggableTable() . ' AS t');
		
		if ($this->model->taggableTableSoftDeletes()) {
			$this->whereNull('t.' . $this->model->getDeletedAtColumn());
		}
		
		return $this;
	}
	
	/**
	 * Set the tag query context to be used by this instance.
	 *
	 * @param mixed $tag_context
	 * 
	 * @return QueryBuilder
	 */
	public function withTagContext($tag_context)
	{
		$this->tag_context = $tag_context;
		
		return $this;
	}
	
	/**
	 * Filter the query on the given tag name(s) or tag model(s).
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return QueryBuilder
	 */
	public function withTag($tag)
	{
		foreach (func_get_args() as $arg) {
			$tags = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($tags as $t) {
				if (is_object($t)) {
					$this->filters[] = array('tag_id' => $t->id);
				}
				else {
					$this->filters[] = array('tag' => $t);
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on the given tag id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public function withTagId($id)
	{
		foreach (func_get_args() as $arg) {
			$tag_ids = (array) $arg;
			
			foreach ($tag_ids as $tag_id) {
				$this->filters[] = array('tag_id' => $tag_id);
			}
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given tag name(s) or tag model(s).
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return QueryBuilder
	 */
	public function withAnyTag($tag)
	{
		$tag_ids = array();
		$tag_names = array();
		
		foreach (func_get_args() as $arg) {
			$tags = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($tags as $t) {
				if (is_object($t)) {
					$tag_ids[] = $t->id;
				}
				else {
					$tag_names[] = $t;
				}
			}
		}
		
		if (!empty($tag_ids)) {
			$this->filters[] = array('tag_id' => $tag_ids);
		}
		if (!empty($tag_names)) {
			$this->filters[] = array('tag' => $tag_names);
		}
		
		return $this;
	}
	
	/**
	 * Filter the query on any of the given tag id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return QueryBuilder
	 */
	public function withAnyTagId($id)
	{
		$tag_ids = array();
		
		foreach (func_get_args() as $arg) {
			$arg = (array) $arg;
			
			foreach ($arg as $tag_id) {
				$tag_ids[] = $tag_id;
			}
		}
		
		$this->filters[] = array('tag_id' => $tag_ids);
		
		return $this;
	}
	
	/**
	 * Set the relationships that should be eager loaded.
	 *
	 * @param  mixed  $relations
	 * @return QueryBuilder
	 */
	public function with($relations)
	{
		if (is_string($relations)) $relations = func_get_args();
		
		$this->relations = array_merge($this->relations, $relations);
		
		return $this;
	}
	
	/**
	 * Apply any tag filters added to this query.
	 * Will return false if any requested tag does not exist.
	 *
	 * @return bool
	 */
	protected function applyTagFilters()
	{
		if (isset($this->tag_filters_applied)) {
			return $this->tag_filters_applied;
		}
		
		if (empty($this->filters)) {
			return $this->tag_filters_applied = false;
		}
		
		$tag_model = $this->model->tagModel();
		$tag_instance = new $tag_model;
		$tag_query = $tag_instance->newQuery();
		
		if (isset($this->tag_context)) {
			if (method_exists($tag_model, 'applyQueryContext')) {
				call_user_func_array(array($tag_model, 'applyQueryContext'), array($tag_query, $this->tag_context));
			}
		}
		
		$filters = $this->filters;
		$tag_query->where(function ($query) use ($filters) {
			foreach ($filters as $filter) {
				if (isset($filter['tag_id'])) {
					if (is_array($filter['tag_id'])) {
						$query->orWhereIn('id', $filter['tag_id']);
					}
					else {
						$query->orWhere('id', $filter['tag_id']);
					}
				}
				else if (isset($filter['tag'])) {
					if (is_array($filter['tag'])) {
						$query->orWhereIn('name', $filter['tag']);
					}
					else {
						$query->orWhere('name', $filter['tag']);
					}
				}
			}
		});
		
		$tags = $tag_query->get();
		$tag_items = $tags->lists('num_items', 'id');
		
		$found = array(
			'tag'    => $tags->lists('id', 'name'),
			'tag_id' => $tags->lists('id', 'id'),
		);
		
		foreach ($filters as &$filter) {
			$type = current(array_keys($filter));
			$value = current($filter);
			
			if (is_array($value)) {
				$intersect = array_intersect(array_values($value), array_keys($found[$type]));
				if (empty($intersect)) {
					return $this->tag_filters_applied = false;
				}
				
				$filter['id'] = Arr::only($found[$type], $intersect);
				$filter['num_items'] = 100000000 + max(Arr::only($tag_items, $filter['id']));
			}
			else {
				if (!array_key_exists($value, $found[$type])) {
					return $this->tag_filters_applied = false;
				}
				
				$filter['id'] = $found[$type][$value];
				$filter['num_items'] = $tag_items[$filter['id']];
			}
		}
		
		usort($filters, function ($a, $b) {
			return ($a['num_items'] < $b['num_items']) ? -1 : 1;
		});
		
		$set_distinct = false;
		
		$first_filter = current(array_splice($filters, 0, 1));
		if (is_array($first_filter['id'])) {
			$this->whereIn('t.tag_id', $first_filter['id']);
			
			if (!$set_distinct) {
				$this->distinct();
				$set_distinct = true;
			}
		}
		else {
			$this->where('t.tag_id', $first_filter['id']);
		}
		
		foreach ($filters as $i => $f) {
			$this->join("{$this->model->taggableTable()} AS t{$i}", function ($join) use ($i, $f) {
				$join_query = $join->on('t.xref_id', '=', "t{$i}.xref_id");
				
				if (!is_array($f['id'])) {
					$join_query->where("t{$i}.tag_id", '=', $f['id']);
				}
			});
			
			if (is_array($f['id'])) {
				$this->whereIn("t{$i}.tag_id", $f['id']);
				
				if (!$set_distinct) {
					$this->distinct();
					$set_distinct = true;
				}
			}
		}
		
		return $this->tag_filters_applied = true;
	}
	
	/**
	 * Execute the query as a "select" statement.
	 *
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function get($columns = array('*'))
	{
		if (!$this->applyTagFilters()) {
			if (!empty($this->aggregate)) {
				return 0;
			}
			return $this->model->newCollection();
		}
		
		if (!empty($this->aggregate)) {
			return parent::get($columns);
		}
		else {
			$results = parent::get(array('t.xref_id'));
		}
		
		if (empty($results)) {
			return $this->model->newCollection();
		}
		
		$xref_ids = array();
		foreach ($results as $result) {
			if (!isset($result->xref_id)) continue;
			$xref_ids[] = $result->xref_id;
		}
		
		if (empty($xref_ids)) {
			return $this->model->newCollection();
		}
		
		$key = $this->model->getKeyName();
		
		$models = $this->model->newQuery()->whereIn($key, $xref_ids)->get();
		
		$models->sortBy(function ($model) use ($xref_ids, $key) {
			foreach ($xref_ids as $idx => $i) {
				if ($model->{$key} == $i) {
					return $idx;
				}
			}
			return 0;
		});
		
		if (!empty($this->relations)) {
			$models->load($this->relations);
		}
		
		return $models;
	}
	
	/**
	 * Execute the query and get the first result.
	 *
	 * @param  array   $columns
	 * @return mixed|\Illuminate\Database\Eloquent\Collection|static
	 */
	public function first($columns = array('*'))
	{
		$results = $this->take(1)->get($columns);
		
		return count($results) > 0 ? $results->first() : null;
	}
	
	/**
	 * Get a paginator for the "select" statement.
	 *
	 * @param  int    $perPage
	 * @param  array  $columns
	 * @return \Illuminate\Pagination\Paginator
	 */
	public function paginate($perPage = null, $columns = array('*'))
	{
		$perPage = $perPage ?: $this->model->getPerPage();
		
		$paginator = $this->connection->getPaginator();
		
		$total = $this->getPaginationCount();
		
		return $paginator->make($this->get($columns)->all(), $total, $perPage);
	}
	
	/**
	 * Update a record in the database.
	 *
	 * @param  array  $values
	 * @return bool
	 */
	public function update(array $values)
	{
		return false;
	}
	
	/**
	 * Delete a record from the database.
	 *
	 * @param  mixed  $id
	 * @return bool
	 */
	public function delete($id = null)
	{
		return false;
	}
}
