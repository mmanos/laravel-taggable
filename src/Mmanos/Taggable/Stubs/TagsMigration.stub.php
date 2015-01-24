<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class {{class}} extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('{{table}}', function (Blueprint $table)
		{
			$table->increments('id');
			$table->string('name');
			$table->integer('num_items')->default(0);
			$table->timestamps();
			$table->softDeletes();
			
			$table->unique('name', 'idx_name');
			$table->index(array('created_at', 'deleted_at', 'num_items'), 'newest_tags');
			$table->index(array('updated_at', 'deleted_at', 'num_items', 'created_at'), 'updated_tags');
			$table->index(array('name', 'deleted_at', 'num_items', 'created_at'), 'alpha_tags');
			$table->index(array('num_items', 'deleted_at', 'created_at'), 'popular_tags');
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('{{table}}');
	}
}
