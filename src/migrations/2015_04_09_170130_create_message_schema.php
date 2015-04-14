<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

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
        $prefix = \Config::get('message-system::config.table_prefix', '');
        $users = \Config::get('message-system::config.users_table', 'users');
        $user_id = \Config::get('message-system::config.users_table_key', 'id');

        Schema::create($prefix.'conversations', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('title', 128)->nullable()->default(null);
            $table->text('user_ids')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create($prefix.'messages', function(Blueprint $table) use ($prefix, $user_id, $users)
        {
            $table->increments('id');
            $table->integer('sender_id')->unsigned();
            $table->integer('conversation_id')->unsigned();
            $table->text('content');
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on($prefix.'conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references($user_id)->on($users)->onDelete('cascade');

            $table->index('sender_id');
            $table->index('conversation_id');

        });

        Schema::create($prefix.'conversation_users', function(Blueprint $table) use ($prefix, $user_id, $users)
        {
            $table->integer('conversation_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();

            $table->primary(array('conversation_id', 'user_id'));

            $table->foreign('conversation_id')->references('id')->on($prefix.'conversations')->onDelete('cascade');
            $table->foreign('user_id')->references($user_id)->on($users)->onDelete('cascade');
        });

        Schema::create($prefix.'messages_status', function(Blueprint $table) use ($prefix, $user_id, $users)
        {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('message_id')->unsigned();
            $table->integer('conversation_id')->unsigned();
            $table->boolean('self');
            $table->integer('status');

            $table->foreign('message_id')->references('id')->on($prefix.'messages')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on($prefix.'conversations')->onDelete('cascade');
            $table->foreign('user_id')->references($user_id)->on($users)->onDelete('cascade');
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
        $prefix = \Config::get('message-system::config.table_prefix', '');

        Schema::drop($prefix.'messages_status');
        Schema::drop($prefix.'conversation_users');
        Schema::drop($prefix.'messages');
        Schema::drop($prefix.'conversations');
    }
}
