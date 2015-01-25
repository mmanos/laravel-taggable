# Tagging Package for Laravel 4

This package adds tagging support to your Laravel application. You can configure it to attach tags to any of your existing Eloquent models.

## Installation

#### Composer

Add this to your composer.json file, in the require object:

```javascript
"mmanos/laravel-taggable": "dev-master"
```

After that, run composer install to install the package.

#### Service Provider

Register the `Mmanos\Taggable\TaggableServiceProvider` in your `app` configuration file.

## Configuration

#### Tags Migration and Model

First you'll need to publish a `tags` table and a `Tag` model. This table will hold a summary of all tags created by your taggable models.

```console
$ php artisan laravel-taggable:tags tags
```

> **Note:** Modify the last parameter of this call to change the table/model name.

> **Note:** You may publish as many tags tables as you need, if you want to keep the tags separate for different types of content, for example.

#### Taggable Migration

Next, publish a migration for each type of content you want to tag. You may tag as many types of content as you wish. For example, if you want to be able to tag both a `users` table and a `blog_posts` table, run this migration once for each table.

```console
$ php artisan laravel-taggable:taggable user_tags
```

#### Run Migrations

Once the migration has been created, simply run the `migrate` command.

#### Model Setup

Next, add the `Taggable` trait to each taggable model definition:

```php
use Mmanos\Taggable\Taggable;

class User extends Eloquent
{
	use Taggable;
}
```

Then you need to specify the tag model as well as the taggable table to use with your model:

```php
class User extends Eloquent
{
	protected $tag_model = 'Tag';
	protected $taggable_table = 'user_tags';
}
```

#### Syncing Custom Attributes

Sometimes you will want to have some of the same fields in your content table synced to the taggable table records. This will allow you to filter and sort by these attributes when querying the taggable table. Luckily this system will automatically sync any fields you define to the taggable table records any time there are changes.

To get started, **modify the taggable migration file** to include your additional fields.

Then, tell your model which fields it needs to sync:

```php
class User extends Eloquent
{
	protected $taggable_table_sync = ['company_id', 'created_at', 'updated_at', 'deleted_at'];
}
```

Now every time you create or update a model, these fields will by synced to all taggable table records for the piece of content.

#### Syncing Deleted Content

This package will automatically delete all taggable table records for a piece of content when that piece of content is deleted.

If you are using the `SoftDeletingTrait` and you are syncing the `deleted_at` column to your taggable table records, this package will automatically soft-delete all taggable table records for a piece of content when that piece of content is deleted. If the content is restored, then the taggable table records are restored as well.

## Working With Tags

#### Tagging Content

To add a tag to an existing piece of content:

```php
$user->tag('Frequent Visitor');
```

Or add multiple tags at once:

```php
$user->tag('Frequent Visitor', 'Happy');
// or
$user->tag(['Frequent Visitor', 'Happy']);
```

> **Note:** If a piece of content already has a tag it will not be added a second time and will not throw an error.

#### Removing Tags

Similarly, you may remove tags from an existing piece of content:

```php
$user->untag('Frequent Visitor');
```

Or remove multiple tags at once:

```php
$user->untag('Frequent Visitor', 'Happy');
// or
$user->untag(['Frequent Visitor', 'Happy']);
```

> **Note:** The system will not throw an error if the content does not have the requested tag.

#### Checking for Tags

To see if a piece of content has a tag:

```php
if ($user->hasTag('Frequent Visitor')) {
	
}
```

#### Retrieving All Tags

To fetch all tags associated with a piece of content, use the `tags` relationship:

```php
$tags = $user->tags;
```

#### Retrieving an Array of All Tags

To fetch all tags associated with a piece of content and return them as an array, use the `tagsArray` method:

```php
$tags = $user->tagsArray();
```

## Querying for Tagged Content

#### Performing Queries

Now let's say you want to query for all content that has a given tag:

```php
$users = User::withTag('Frequent Visitor')->take(10)->get();
```

These queries extend the same `QueryBuilder` class that you are used to working with, so all of those methods work as well:

```php
$users = User::withTag('Frequent Visitor')
	->where('tag_created_at', '>', '2015-01-01 00:00:00')
	->with('company')
	->orderBy('tag_created_at', 'desc')
	->paginate(10);
```

> **Note:** The `update` and `delete` methods on a QueryBuilder object do not work for these queries.

You may query for content that has more than one tag:

```php
$users = User::withTag('Frequent Visitor', 'Happy')->get();
// or
$users = User::withTag(['Frequent Visitor', 'Happy'])->get();
```

You may also query for content that has any of the given tags:

```php
$users = User::withAnyTag('Frequent Visitor', 'Happy')->get();
// or
$users = User::withAnyTag(['Frequent Visitor', 'Happy'])->get();
```

> **Note:** Query performance can be reduced for these types of queries if your queries match thousands of records or more.

And you may combine multiple filters:

```php
// Fetch all users who have the 'Agent' tag and who have 'Frequent Visitor' or 'Happy'.
$users = User::withTag('Agent')->withAnyTag('Frequent Visitor', 'Happy')->get();
```

#### Tag Contexts

Sometimes you might want to associate your tags (summary table) records with some custom context for your application. For example, say you have a `companies` table and a `users` table and each user belongs to a company. And now you also want to associate each tag record with a company allowing you to fetch all tags used by each individual company. In order to do so, we have to tell this package to be aware of this company context and modify it's queries accordingly.

To get started, make sure you **modify your tags migration** to include any context fields (`company_id`, in this case). You might also need to update the unique index, if necessary.

Then modify your taggable model by adding a `tagContext` method:

```php
class User extends Eloquent
{
	public function tagContext()
	{
		return $this->company;
	}
}
```

Next modify your `Tag` model (or whatever name you specified during configuration) to apply any contexts:

```php
class Tag extends Eloquent
{
	public static function applyQueryContext($query, $context)
	{
		$query->where('company_id', $context->id);
	}
	
	public static function applyModelContext($model, $context)
	{
		$model->company_id = $context->id;
	}
}
```

The `applyQueryContext` method will adjust any tag queries used by this package to filter on `company_id`.

The `applyModelContext` method is called when creating a new `Tag` record and should set any required context fields.

Finally, when performing queries, specify the context to apply:

```php
$users = User::withTag('Frequent Visitor')->withTagContext($company)->take(10)->get();
```
