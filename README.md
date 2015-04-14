Laravel User Messaging system
===============================

PHP multi user multi conversations messaging system, just like facebook reworked original tzookb/tbmsg package. Currently I am using Laravel 4.2 so this package as is supports Laravel 4.2.

Changes from Original Package
-----------------------------
I needed something that is more efficient in terms of database use, even if at the expense of sacrificing Eloquent portability. I use mysql. A single class encompasses DB access: MessageRepository, so it is easy to modify if you need to for use with another DB engine.

All fluff which was not providing useful function was removed and the classes were rewritten with this in mind. Also renamed the package and classes in it since this is not a drop-in replacement for the original package.

The changes include:
- renamed package to vsch/message-system.
- renamed all classes that were prefixed with TBMsg to SystemMessage prefix.
- renamed TBMsg alias to Msg.
- removed myriad of directories that are not needed and add no value on a small package as this. 
- removed all wrapper classes for the same reason. Manipulation of the messages is done through the MessageRepository or the Msg:: alias which amounts to the same thing. 
- removed the repository interface and duplication of message status constants. 
- removed db manipulations from the Msg:: alias and moved them to the MessageRepository.  
- removed exception throwing when getting conversation(s) between users and replaced with null return if none exist. 
- changed get conversation for two users to generic getConversationsBetweenUsers that takes an array of user ids or comma separated id list and returns result set of conversations that have this exact participant list, sorted in reverse order of creation so the first one is the latest.
- changed return from getUserConversations is now a list of conversation messages that the user has not read instead of just the last message. Additionally the result is a raw query result set instead of elaborately wrapped collection containing the same data.
- removed tests since they no longer apply. Will rewrite them when I get a chance later.
- added an optional title to conversations.
- moved all migrations to a single file to control the order of table creation to allow for foreign key constraints. 
- added foreign keys to migrations and removed unnecessary joins from all queries that did nothing but ensure user ids exist in the users table. This is ensured by foreign keys.
- added removeUsersFromConversations() to allow users to remove themselves from a conversation so as to no longer receive messages from that conversation
- added addUsersToConversations() to allow adding users to a conversation
- added modifyUsersInConversations() to allow modifying users in conversations
- added parameter to functions that create Conversation or Message so that they could return an instance of a subclass. That way the models can be customized by end user on their project.
- added use of table prefix and user table config to the migration. No need to edit migration file to change the prefix
- changed default prefix for tables to `vms_`.
- changed status constants so that UNREAD is 0, READ is 1, ARCHIVED is 2 and DELETED is 3. Which seems like a logical progression of a message's status throughout its lifetime. 
- rewrote all queries in terms of DB access without Eloquent overhead of models.
- de-normalized the DB model for quicker access with less joins. Now the conversation has a comma separated list of user ids of participants.
- added initialization of $table field in all models to the actual table name as defined by config.php 'table_prefix' setting. Now models work correctly regardless of prefix.
- added updating of the conversation participants from the Conversation Model's user_ids field. Changing the comma separated list in the Conversation Model and saving, changes the messages_status and conversation_users tables.
- added handling of READ and WRITE db connections where objects are created and then immediately queried. In that case the latter queries are run on the WRITE connection so as to avoid replication delay related errors.
- changed config values to use snake_case convention to match the rest of Laravel config files
- moved all MessageRepository configuration access into MessageRepository from MessageServiceProvider, now MessageRepository constructor takes an array of config values. 
- added Config value for 'users_table_display' value which gives the name of the field in the users table to use for identifying the user for display purposes. Default is 'id' but should be whatever field you have for the users, like name or user_name, etc. This field is added to retrieved messages as `sender` and for `|` separated list of participants in the conversation's `participants` field.

**TODO**:
- add RESTful controller for message access and manipulation via ajax, with PHP, javascript and HTML code snippets that could be easily integrated into any project.
- add conversation owner to allow adding/removing users from conversations, merging owned conversations, etc.
- add blocked_users table to hold blocked users list for any user to exclude messages from blocked users and exclude blocked users from seeing messages by whom they were blocked
- add mergeConversations() to allow for merging of several conversations and participants of these into a single conversation. All existing messages are added for users that had no access to them prior to the merge.
- add complaints functionality if a message is offensive, the message as it is displayed should be copied in the complaint.

Description:
----------------

User messaging system based on multiple conversations between N users. 

Features:
---------

Basic messaging functionality allowing for multiple conversations between multiple users. Currently no user interface is included which is a serious omission from the package, since the backend functionality is trivial.

How it is built:
----------------
The default prefix for all tables is 'vms_', this setting is configured in the config.php file.

vms_messages table for messages and their content.

vms_conversations table for all the conversations in the system.

vms_conversation_users table for the conversation users for each conversation. 

vms_messages_status table for per user status for each message.

Installation:
----------------
1. Add this: `"vsch/message-system": "1.*"` to your composer.json file
2. run: `composer update vsch/message-system`
3. Now add the service provider to your app.php file: `'Vsch\MessageSystem\MessageServiceProvider'`
4. If you register the Facade then you can access the message system via Msg:: alias, in your app.php file add: `'Msg' => 'Vsch\MessageSystem\Facade\Msg'`
5. publish the package config `php artisan config:publish vsch/message-system` to add packages/vsch/message-system/config.php to your project
6. There is only one migration file and it contains migration for the necessary schema for the message-system: `2015_04_09_170130_create_message_schema.php`
7. You don't need to publish the package migration just to add a prefix. The migration uses the configuration prefix for table names. To copy the migration file to your project run: `php artisan migrate:publish vsch/message-system`
8. After editing the config file to adjust the default prefix of 'vms_' you need to run `php artisan migrate` to create the message system schema objects.

How to use it:
----------------
TODO: add user interface part of the system to have a starting point from which users can customize the system.
