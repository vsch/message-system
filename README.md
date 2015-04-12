Laravel User Messaging system
===============================

PHP multi user multi conversations messaging system, just like facebook reworked original tzookb/tbmsg package.

Changes from Original Package
-----------------------------
I needed something that is more efficient in terms of database use at the expense of sacrificing Eloquent portability. I use mysql. A single class encompases DB access MessageRepository so it is easy to modify if you need to.

All unnecessary fluff was removed and the classes were rewritten with this in mind. Also renamed the package and classes in it since this is not a drop-in replacement for the original package.

The changes include:
- Renamed package to vsch/messagesystem.
- renamed all classes that were prefixed with TBMsg to SystemMessage prefix.
- renamed tbmsg alias to msg.
- myriad of directories that are not needed and add no value on a small package as this. 
- removed all wrapper classes for the same reason. Manipulation of the messages is done through the MessageRepository or the Msg:: alias which amounts to the same thing. 
- removed of the Repostory Interface and resulting duplication of message status constants. 
- removed exception throwing when getting conversation(s) between users and replaced with null return if none exist. 
- changed get conversation for two users to generic getConversationsBetweenUsers that takes an array of user ids or comma separated id list and returns result set of conversations that have this exact participant list.
- return from get conversations for user is a list of conversation messages that the user has not read instead of just the last message. Additionally the result is a raw query result set instead of elaboarately wrapped collection containing the same data.
- removed tests which no longer apply. Will rewrite them when I get a chance later.
- removed db manipulations from the Msg:: alias and moved them to the MessageRepository.  
- added an optional title to conversations.
- moved all migrations to a single file to control the order of table creation to allow for foreign key constraints. 
- added foreign keys to migrations and removed unnecessary joins from all queries that did nothing but ensure user ids exist in the users table. This is ensured by foreign keys.
- added removeUsersFromConversations() to allow users to remove themselves from a conversation so as to no longer receive messages from that conversation
- added addUsersToConversations() to allow adding users to a conversation after the fact
- added removeUsersFromConversations() to allow removing users from conversations after the fact
- added modifyUsersInConversations() to allow modifying users in conversations after the fact
- added parameter to functions that create Conversation or Message so that they return an instance of a subclass that way the models can be customized by end user on their project, like me.
- added use of table prefix and user table config to the migration. No need to edit migration file just to use a prefix
- added default prefix of vms_ to messaging system.

**TODO**:
- add conversation owner to allow adding/removing users from conversations, merging owned conversations, etc.
- add blocked_users table to hold blocked users list for any user to exclude messages from blocked users and exclude blocked users from seeing messages by whom they were blocked
- add mergeConversations() to allow for merging of several conversations and participants of these into a single conversation. All existing messages are added for users that had no access to them prior to the merge.
- add complaints functionality if someone's message is offensive, the message as it is displayed should be copied in the complaint.

Description:
----------------

User messaging system based on conversations between N users.  

Features:
---------

Basic messaging functionality allowing for multiple conversations between multiple users.

How it is built:
----------------

messages table for messages and their content.

conversations table for all the conversations in the system.

conversation_users table for the conversation users for each conversation 

messages_status table for per user status for each message

Installation:
----------------
1. Add this: '"vsch/message-system": "1.*"' to your composer.json file
2. run: "composer update vsch/message-system"
3. Now add the service provider to your app.php file: "'Vsch\MessageSystem\MessageServiceProvider'"
4. If you register the Facade then you can access the message system via Msg:: alias, in your app.php file add: "'Msg' => 'Vsch\MessageSystem\Facade\Msg'"
5. publish the package configs "php artisan config:publish vsch/message-system" to add packages/vsch/message-system/config.php to your project
6. you don't need to publish the package migration just to add a prefix. The migration uses the configuration prefix for table names. However, you can modify the files :  "php artisan migrate:publish vsch/message-system"
7. there is only one migration file and it contains migration for the necessary schema for the message-system: 2015_04_09_170130_create_message_schema.php
8. After editing message-system specific config file to adjust the default prefix of 'vms_' you can run `php artisan migrate` to create the message system schema objects.

How to use it:
----------------
TODO: add user interface part of the system to have a starting point from which users can customize the system.

