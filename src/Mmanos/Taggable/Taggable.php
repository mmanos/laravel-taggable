<?php namespace Mmanos\Taggable;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model as Eloquent;

trait Taggable
{
	/**
	 * Trait boot method called by parent model class.
	 *
	 * @return void
	 */
	public static function bootTaggable()
	{
		static::saved(function ($model) {
			$model->syncTaggedTableAttributes();
		});
		
		static::deleted(function ($model) {
			$model->handleDeletedModelTags();
		});
		
		static::registerModelEvent('restored', function ($model) {
			$model->handleRestoredModelTags();
		});
	}
	
	/**
	 * Return the tag class for this model.
	 *
	 * @return string
	 */
	public function tagModel()
	{
		return $this->tag_model;
	}
	
	/**
	 * Return the tagged table name for this model.
	 *
	 * @return string
	 */
	public function taggableTable()
	{
		return $this->taggable_table;
	}
	
	/**
	 * Define the tags relationship for this model.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function tags()
	{
		return $this->belongsToMany($this->tagModel(), $this->taggableTable(), 'xref_id')->withPivot('tag_created_at');
	}
	
	/**
	 * Return an array of all tag names associated with this model.
	 *
	 * @return array
	 */
	public function tagsArray()
	{
		$tags = array();
		
		foreach ($this->tags as $tag) {
			$tags[] = $tag->name;
		}
		
		return $tags;
	}
	
	/**
	 * Returns true if the current model has the given tag name (or tag model).
	 *
	 * @param Eloquent|string $tag
	 * 
	 * @return bool
	 */
	public function hasTag($tag)
	{
		$found = $this->tags->filter(function ($t) use ($tag) {
			if (is_object($tag)) {
				return $t->id == $tag->id;
			}
			else {
				return $t->name == $tag;
			}
		});
		
		return !$found->isEmpty();
	}
	
	/**
	 * Add one or more tags to the current model.
	 * Can be either a tag name, a tag model, a collection of models, or an array of models.
	 * Will create the tag if not found.
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return Eloquent
	 */
	public function tag($tag)
	{
		foreach (func_get_args() as $arg) {
			$tags = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($tags as $t) {
				if (!is_object($t)) {
					$t = $this->findTagByNameOrCreate($t);
				}
				
				if (!$t || !$t instanceof Eloquent) {
					continue;
				}
				
				if ($this->hasTag($t)) {
					continue;
				}
				
				$this->tags()->attach($t, array_merge($this->taggableTableSyncAttributes(), array(
					'tag_created_at' => date('Y-m-d H:i:s'),
				)));
				
				$t->increment('num_items');
				
				$this->tags->add($t);
			}
		}
		
		return $this;
	}
	
	/**
	 * Remove one or more tags from the current model.
	 * Can be either a tag name, a tag model, a collection of models, or an array of models.
	 * Will remove all tags if no paramter is passed.
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return Eloquent
	 */
	public function untag($tag = null)
	{
		$args = func_get_args();
		
		if (0 == count($args)) {
			$args[] = $this->tags;
		}
		
		foreach ($args as $arg) {
			$tags = ($arg instanceof Collection) ? $arg->all() : is_array($arg) ? $arg : array($arg);
			
			foreach ($tags as $t) {
				if (!is_object($t)) {
					$t = $this->findTagByName($t);
				}
				
				if (!$t || !$t instanceof Eloquent) {
					continue;
				}
				
				if (!$this->hasTag($t)) {
					return;
				}
				
				$this->tags()->detach($t);
				
				$t->decrement('num_items');
				
				foreach ($this->tags as $idx => $cur_tag) {
					if ($cur_tag->getKey() == $t->getKey()) {
						$this->tags->pull($idx);
						break;
					}
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Return a new tag table query.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	private function newTagQuery()
	{
		$tag_model = $this->tagModel();
		$tag_instance = new $tag_model;
		
		$query = $tag_instance->newQuery();
		
		if (method_exists($this, 'tagContext')) {
			if (method_exists($tag_model, 'applyQueryContext')) {
				call_user_func_array(
					array($tag_model, 'applyQueryContext'),
					array($query, $this->tagContext())
				);
			}
		}
		
		return $query;
	}
	
	/**
	 * Find a tag from the given name.
	 *
	 * @param string $name
	 * 
	 * @return Eloquent|null
	 */
	private function findTagByName($name)
	{
		return $this->newTagQuery()->where('name', $name)->first();
	}
	
	/**
	 * Find a tag from the given name or create it if not found.
	 *
	 * @param string $name
	 * 
	 * @return Eloquent
	 */
	private function findTagByNameOrCreate($name)
	{
		if ($tag = $this->findTagByName($name)) {
			return $tag;
		}
		
		$tag_model = $this->tagModel();
		
		$tag = new $tag_model;
		$tag->name = $name;
		$tag->num_items = 0;
		
		if (method_exists($this, 'tagContext')) {
			if (method_exists($tag_model, 'applyModelContext')) {
				call_user_func_array(
					array($tag_model, 'applyModelContext'),
					array($tag, $this->tagContext())
				);
			}
		}
		
		$tag->save();
		
		return $tag;
	}
	
	/**
	 * Return an array of model attributes to sync on the taggable_table records.
	 *
	 * @return array
	 */
	private function taggableTableSyncAttributes()
	{
		if (!isset($this->taggable_table_sync)) {
			return array();
		}
		
		$attributes = array();
		foreach ($this->taggable_table_sync as $attr) {
			$attributes[$attr] = $this->getAttribute($attr);
		}
		
		return $attributes;
	}
	
	/**
	 * Returns whether or not we are soft-deleting tagged table records.
	 *
	 * @return bool
	 */
	public function taggableTableSoftDeletes()
	{
		if (method_exists($this, 'getDeletedAtColumn')) {
			if (array_key_exists($this->getDeletedAtColumn(), $this->taggableTableSyncAttributes())) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Sync tagged table attributes to all tags associated with this model.
	 *
	 * @return void
	 */
	public function syncTaggedTableAttributes()
	{
		if (empty($this->taggableTableSyncAttributes())) {
			return;
		}
		
		DB::table($this->taggableTable())
			->where('xref_id', $this->getKey())
			->update($this->taggableTableSyncAttributes());
	}
	
	/**
	 * Delete tagged table records for this current model since it was just deleted.
	 *
	 * @return void
	 */
	private function handleDeletedModelTags()
	{
		if ($this->taggableTableSoftDeletes()) {
			foreach ($this->tags as $tag) {
				$this->syncTaggedTableAttributes();
				$tag->decrement('num_items');
			}
			
			return;
		}
		
		$this->untag();
	}
	
	/**
	 * Restore tagged table records for this current model since it was just restorede.
	 *
	 * @return void
	 */
	private function handleRestoredModelTags()
	{
		if (!$this->taggableTableSoftDeletes()) {
			return;
		}
		
		foreach ($this->tags as $tag) {
			$this->syncTaggedTableAttributes();
			$tag->increment('num_items');
		}
	}
	
	/**
	 * Begin querying the model's tagging table and filter on the given tag name(s) or tag model(s).
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function withTag($tag)
	{
		return call_user_func_array(array(static::queryTags(), 'withTag'), func_get_args());
	}
	
	/**
	 * Begin querying the model's tagging table and filter on the given tag id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function withTagId($id)
	{
		return call_user_func_array(array(static::queryTags(), 'withTagId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's tagging table and filter on any of the given tag name(s) or tag model(s).
	 *
	 * @param Eloquent|string|Collection|array $tag
	 * 
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function withAnyTag($tag)
	{
		return call_user_func_array(array(static::queryTags(), 'withAnyTag'), func_get_args());
	}
	
	/**
	 * Begin querying the model's tagging table and filter on any of the given tag id(s).
	 *
	 * @param int|array $id
	 * 
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function withAnyTagId($id)
	{
		return call_user_func_array(array(static::queryTags(), 'withAnyTagId'), func_get_args());
	}
	
	/**
	 * Begin querying the model's tagging table.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function queryTags()
	{
		$model = new static;
		
		$conn = $model->getConnection();
		$query = new TagQueryBuilder(
			$conn,
			$conn->getQueryGrammar(),
			$conn->getPostProcessor()
		);
		
		$query->setModel($model);
		
		return $query;
	}
}
