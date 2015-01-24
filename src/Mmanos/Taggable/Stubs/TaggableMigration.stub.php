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
			$table->integer('xref_id')->unsigned();
			$table->integer('tag_id')->unsigned();
			$table->timestamp('tag_created_at');
			
			$table->unique(array('xref_id', 'tag_id'), 'xref_tag');
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
