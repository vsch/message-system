<?php

use Illuminate\Database\Migrations\Migration;

class CreateMessageSchema extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public
    function up()
    {
        Schema::create('conversations', function ($table)
        {
            $table->increments('id');
            $table->string('title', 128)->nullable()->default(null);
            $table->text('user_ids')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('messages', function($table)
        {
            $table->increments('id');
            $table->integer('sender_id')->unsigned();
            $table->integer('conversation_id')->unsigned();
            $table->text('content');
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('sender_id');
            $table->index('conversation_id');

        });

        Schema::create('conversation_users', function($table)
        {
            $table->integer('conversation_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();

            $table->primary(array('conversation_id', 'user_id'));

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('messages_status', function($table)
        {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('message_id')->unsigned();
            $table->integer('conversation_id')->unsigned();
            $table->boolean('self');
            $table->integer('status');

            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public
    function down()
    {
        Schema::drop('messages_status');
        Schema::drop('conversation_users');
        Schema::drop('messages');
        Schema::drop('conversations');
    }
}
