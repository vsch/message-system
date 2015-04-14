<?php
/**
 * Created by PhpStorm.
 * User: tzookb
 * Date: 7/6/14
 * Time: 10:58 AM
 */

namespace Vsch\MessageSystem;

use Illuminate\Database\DatabaseManager;
use Vsch\MessageSystem\Models\Message;
use Vsch\MessageSystem\Models\Conversation;

class MessageRepository
{
    const UNREAD = 0;
    const READ = 1;
    const ARCHIVED = 2;
    const DELETED = 3;

    protected $users_table;
    protected $users_table_key;
    protected $table_prefix;
    protected $users_table_display;
    /**
     * @var DatabaseManager
     */
    private $db;

    protected $config;
    protected $conversation_users;
    protected $messages;
    protected $messages_status;
    protected $conversations;

    protected
    function getConfig($key, $default = null)
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    public
    function __construct($config, DatabaseManager $db)
    {
        $this->config = $config;
        $this->db = $db;

        $table_prefix = $this->getConfig('table_prefix', '');
        $this->table_prefix = $table_prefix;

        $this->users_table = $this->getConfig('users_table', 'users');
        $this->users_table_key = $this->getConfig('users_table_key', 'id');
        $this->users_table_display = $this->getConfig('users_table_display', 'id');

        foreach (['conversation_users', 'messages', 'messages_status', 'conversations',] as $table)
        {
            $this->$table = $table_prefix . $table;
        }
    }

    public
    function createConversation($user_ids, $title = null, $class = null)
    {
        if (!is_array($user_ids)) $user_ids = explode(',', $user_ids);
        array_multisort($user_ids, SORT_NUMERIC);
        $user_ids = implode(',', $user_ids);

        //create new conv
        if ($class === null) $class = '\Vsch\MessageSystem\Models\Conversation';
        $conv = new $class();
        $conv->title = $title;
        $conv->user_ids = $user_ids;

        $this->db->beginTransaction();
        try
        {
            if ($conv->save())
            {
                $count = $this->db->insert(<<<SQL
INSERT INTO $this->conversation_users (conversation_id, user_id)
SELECT ?, $this->users_table_key FROM $this->users_table WHERE $this->users_table_key IN ($user_ids)

SQL
                    , [$conv->id]);
                $this->db->commit();
            }
            else
            {
                $this->db->rollBack();
                $conv = null;
            }
        }
        catch (Exception $e)
        {
            $this->db->rollBack();
            throw $e;
        }

        return $conv;
    }

    public
    function addMessageToConversation($conversation_id, $user_id, $content, $class = null)
    {
        //check if user of message is in conversation, first check read connection
        if (!$this->isUserInConversation($conversation_id, $user_id))
        {
            // before tossing our lunch, lets see if user is in conversation using the write connection
            if (!$this->isUserInConversation($conversation_id, $user_id, false))
            {
                throw new UserNotInConversationException;
            }
        }

        //if so add new message
        if ($class === null) $class = '\Vsch\MessageSystem\Models\Message';
        $message = new $class();
        $message->sender_id = $user_id;
        $message->conversation_id = $conversation_id;
        $message->content = $content;

        $this->db->beginTransaction();
        try
        {
            if ($message->save())
            {
                // rewrote this as an sql query without PHP loop or at least eloquent involvement
                $read = self::READ;
                $unread = self::UNREAD;

                $this->db->insert(<<<SQL
INSERT INTO $this->messages_status (user_id, message_id, conversation_id, self, status)
SELECT cu.user_id, ?, cu.conversation_id, (cu.user_id = ?), CASE WHEN cu.user_id = ? THEN $unread ELSE $unread END
FROM $this->conversation_users cu WHERE cu.conversation_id = ?

SQL
                    , [$message->id, $message->sender_id, $message->sender_id, $message->conversation_id]);
                $this->db->commit();
            }
            else
            {
                $message = null;
                $this->db->rollBack();
            }
        }
        catch (Exception $e)
        {
            $this->db->rollBack();
            throw $e;
        }

        return $message;
    }

    public
    function getConversationsBetweenUsers($user_ids)
    {
        if (!is_array($user_ids)) $user_ids = explode(',', $user_ids);
        array_multisort($users_ids, SORT_NUMERIC);
        $users_ids = implode(',', $users_ids);

        if (is_array($user_ids)) $user_ids = implode(',', $user_ids);
        $user_count = substr_count($user_ids, ',') + 1;

        $results = $this->db->select(<<<SQL
SELECT GROUP_CONCAT(cnvs.id SEPARATOR ',') conversation_ids
FROM $this->conversations cnvs
WHERE EXISTS (select * from $this->conversation_users cu where cu.conversation_id = cnvs.id and cu.user_id in ($user_ids))
    AND cnvs.user_ids = ?
ORDER BY cnvs.id DESC

SQL
            , [$user_ids]);

        return !empty($results) ? $results[0]->conversation_ids : null;
    }

    public
    function markMessageAs($msg_id, $user_id, $status)
    {
        $andWhere = " AND user_id = $user_id";

        if ($status === GeoipRepository::DELETED)
        {
            // if sender deletes the message then delete for all
            $rows = $this->db->select("SELECT * FROM $this->messages WHERE sender_id = $user_id and id = $msg_id");
            if (!empty($rows))
            {
                $andWhere = '';
            }
        }

        $this->db->statement(<<<SQL
UPDATE $this->messages_status
SET status = $status
WHERE message_id = $msg_id$andWhere
SQL
        );
    }

    public
    function markMessageAsRead($msg_id, $user_id)
    {
        $this->markMessageAs($msg_id, $user_id, self::READ);
    }

    public
    function markMessageAsUnread($msg_id, $user_id)
    {
        $this->markMessageAs($msg_id, $user_id, self::UNREAD);
    }

    public
    function markMessageAsDeleted($msg_id, $user_id)
    {
        $this->markMessageAs($msg_id, $user_id, self::DELETED);
    }

    public
    function markMessageAsArchived($msg_id, $user_id)
    {
        $this->markMessageAs($msg_id, $user_id, self::ARCHIVED);
    }

    public
    function isUserInConversation($conversation_id, $user_id, $useReadPdo = true)
    {
        $results = $this->db->select(<<<SQL
SELECT cu.user_id FROM $this->conversation_users cu WHERE cu.user_id = $user_id AND cu.conversation_id = $conversation_id LIMIT 1

SQL
            , [], $useReadPdo);

        return !empty($results);
    }

    public
    function getUsersInConversation($conversation_id)
    {
        $results = $this->db->select(<<<SQL
SELECT GROUP_CONCAT(DISTINCT cu.user_id SEPARATOR ',') user_ids
FROM $this->conversation_users cu
WHERE cu.conversation_id = $conversation_id

SQL
        );

        $user_ids = !empty($results) ? $results[0]->user_ids : '';
        return $user_ids;
    }

    public
    function getUnreadMessageCount($user_id)
    {
        $results = $this->db->select(<<<SQL
SELECT COUNT(mst.id) AS num_unread
FROM $this->messages_status mst
WHERE mst.user_id = $user_id AND mst.status = 0

SQL
        );
        return !empty($results) ? $results[0]->num_unread : 0;
    }

    public
    function markAllMessagesInConversationAs($conversation_id, $user_id, $status, $currStatusList = null)
    {
        $currWhere = $currStatusList === null ? '' : " AND status IN ($currStatusList)";

        $this->db->statement(<<<SQL
UPDATE $this->messages_status SET status = $status
WHERE user_id = $user_id $currWhere AND message_id IN (SELECT id FROM $this->messages WHERE conversation_id = $conversation_id)

SQL
        );
    }

    public
    function getConversationMessages($conversation_id, $user_id, $exclude_status = null, $newToOld = null)
    {
        $order_by = $newToOld || $newToOld === null ? 'desc' : 'asc';
        if ($exclude_status === null) $exclude_status = self::DELETED . ',' . self::ARCHIVED . ',' . self::READ;

        $result = $this->db->select(<<<SQL
SELECT msg.id, msg.content, mst.status, msg.created_at, msg.updated_at, msg.sender_id
FROM $this->messages_status mst
    INNER JOIN $this->messages msg ON mst.message_id=msg.id
WHERE msg.conversation_id = $conversation_id AND mst.user_id = $user_id AND mst.status NOT IN ($exclude_status)
ORDER BY msg.created_at $order_by

SQL
        );

        return $result;
    }

    public
    function getConversations($user_id, $exclude_status = null)
    {
        if ($exclude_status === null) $exclude_status = self::DELETED . ',' . self::ARCHIVED . ',' . self::READ;

        return $this->db->select(<<<SQL
SELECT cnvs.id conversation_id, cnvs.title as conversation_title, cnvs.user_ids conversation_user_ids, null created_at, null updated_at, null id, null content, null status, null self, null sender_id, null sender,
(SELECT GROUP_CONCAT($this->users_table_display SEPARATOR '|') user_names FROM $this->users_table
    WHERE $this->users_table_key IN (SELECT user_id FROM $this->conversation_users cu WHERE cu.conversation_id = cnvs.id)) conversation_participants
FROM $this->conversations cnvs
WHERE EXISTS (SELECT * FROM $this->conversation_users cu WHERE cu.conversation_id = cnvs.id AND cu.user_id = $user_id)
UNION ALL
SELECT msg.conversation_id, cnvs.title as conversation_title, cnvs.user_ids conversation_user_ids, msg.created_at, msg.updated_at, msg.id id, msg.content, mst.status, mst.self, msg.sender_id, us.$this->users_table_display sender, null conversation_participants
FROM $this->messages msg
    INNER JOIN $this->conversations cnvs ON msg.conversation_id = cnvs.id
    INNER JOIN $this->messages_status mst ON msg.id = mst.message_id
    INNER JOIN $this->users_table us ON msg.sender_id = us.$this->users_table_key
WHERE mst.user_id = $user_id AND mst.status NOT IN ($exclude_status)
ORDER BY conversation_id, created_at ASC

SQL
        );
    }

    public
    function getUsersInConversations($convsIds)
    {
        return $this->db->select(<<<SQL
SELECT cu.conversation_id, cu.user_id
FROM $this->conversation_users cu
WHERE cu.conversation_idIN ($convsIds)

SQL
        );
    }

    public
    function removeUsersFromConversations($conversation_ids, $user_ids)
    {
        if (is_array($conversation_ids)) $conversation_ids = implode(',', $conversation_ids);
        if (is_array($user_ids)) $user_ids = implode(',', $user_ids);

        // make sure we have no extra conversation ids where the user is no longer participating
        $keepConvs = $this->db->select("SELECT GROUP_CONCAT(DISTINCT conversation_id SEPARATOR ',') conversation_ids FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids) and user_id in ($user_ids)");
        if (!empty($keepConvs) && ($conversation_ids = $keepConvs[0]->conversation_ids) !== '' && $conversation_ids !== null)
        {
            $this->db->beginTransaction();
            try
            {
                $keepConvs = $this->db->select("SELECT GROUP_CONCAT(DISTINCT conversation_id SEPARATOR ',') conversation_ids FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids) and user_id not in ($user_ids)");
                if (!empty($keepConvs) && ($conversationIds = $keepConvs[0]->conversation_ids) !== '' && $conversationIds !== null)
                {
                    $keepMessages = $this->db->select("SELECT GROUP_CONCAT(DISTINCT id SEPARATOR ',') message_status_ids FROM $this->messages_status WHERE conversation_id IN ($conversation_ids) AND user_id NOT IN ($user_ids)");
                    if (!empty($keepMessages) && ($messageStatusIds = $keepMessages[0]->message_status_ids) !== '' && $messageStatusIds !== null)
                    {
                        $this->db->delete("DELETE FROM $this->messages_status WHERE id NOT IN ($messageStatusIds)");
                        //$this->db->delete("DELETE FROM $this->messages WHERE message_id NOT IN ($messageIds)");
                        $this->db->delete("DELETE FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids) AND user_id IN ($user_ids)");
                    }
                    else
                    {
                        $this->db->delete("DELETE FROM $this->messages_status WHERE conversation_id IN ($conversation_ids)");
                        //$this->db->delete("DELETE FROM $this->messages WHERE conversation_id IN ($convIds)");
                        //$this->db->delete("DELETE FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids)");
                        $this->db->delete("DELETE FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids) AND user_id IN ($user_ids)");
                        //$this->db->delete("DELETE FROM $this->conversations WHERE id IN ($convIds)");
                    }
                }
                else
                {
                    $this->db->delete("DELETE FROM $this->messages_status WHERE conversation_id IN ($conversation_ids)");
                    //$this->db->delete("DELETE FROM $this->messages WHERE conversation_id IN ($convIds)");
                    $this->db->delete("DELETE FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids)");
                    //$this->db->delete("DELETE FROM $this->conversations WHERE id IN ($convIds)");
                }

                $this->db->update(<<<SQL
UPDATE $this->conversations cnvs SET user_ids = (SELECT GROUP_CONCAT(user_id ORDER BY user_id SEPARATOR ',') FROM $this->conversation_users cu WHERE cu.conversation_id = cnvs.id)
WHERE cnvs.id in ($conversation_ids)

SQL
                );

                $this->db->commit();
            }
            catch (Exception $e)
            {
                $this->db->rollBack();
                throw $e;
            }
        }
    }

    public
    function addUsersToConversations($conversation_ids, $user_ids)
    {
        if (is_array($conversation_ids)) $conversation_ids = implode(',', $conversation_ids);
        if (is_array($user_ids)) $user_ids = implode(',', $user_ids);

        // make sure we have no extra conversation ids where the user is no longer participating
        if (!empty($conversation_ids) && !empty($user_ids))
        {
            $this->db->beginTransaction();
            try
            {
                // add conversation_users where they don't exist
                $this->db->update(<<<SQL
INSERT INTO $this->conversation_users (conversation_id, user_id)
SELECT * FROM
(
    select cnvs.id conversation_id, usrs.$this->users_table_key user_id from $this->conversations cnvs cross join $this->users_table usrs
    where cnvs.id in ($conversation_ids) and usrs.$this->users_table_key in ($user_ids)
) cu
WHERE NOT exists(select * from $this->conversation_users cu2 where cu2.conversation_id = cu.conversation_id and cu2.user_id = cu.user_id)

SQL
                );

                // then add messages_status for these users and mark as unread
                $this->db->update(<<<SQL
INSERT INTO $this->messages_status (conversation_id, user_id, message_id, self, status)
SELECT * FROM
(
    select cnvs.id conversation_id, usrs.$this->users_table_key user_id, msgs.id message_id, 0 self, 0 status
    from ($this->conversations cnvs
        inner join $this->messages msgs on cnvs.id = msgs.conversation_id)
        cross join $this->users_table usrs
    where cnvs.id in ($conversation_ids) and usrs.$this->users_table_key in ($user_ids)
) ms
WHERE NOT exists(select * from $this->messages_status ms2
                    where ms2.conversation_id = ms.conversation_id
                        and ms2.user_id = ms.user_id
                        and ms2.message_id = ms.message_id)

SQL
                );

                $this->db->update(<<<SQL
UPDATE $this->conversations cnvs SET user_ids = (SELECT GROUP_CONCAT(user_id ORDER BY user_id SEPARATOR ',') FROM $this->conversation_users cu WHERE cu.conversation_id = cnvs.id)
WHERE cnvs.id in ($conversation_ids)

SQL
                );

                $this->db->commit();
            }
            catch (Exception $e)
            {
                $this->db->rollBack();
                throw $e;
            }
        }
    }

    public
    function getCleanConversationIds($conversation_ids)
    {
        $row = $this->db->select("SELECT GROUP_CONCAT(DISTINCT id SEPARATOR ',') conversation_ids FROM $this->conversations WHERE id IN ($conversation_ids)");
        return empty($row) ? '' : $row[0]->conversation_ids;
    }

    public
    function getCleanUserIds($user_ids)
    {
        $row = $this->db->select("SELECT GROUP_CONCAT(DISTINCT $this->users_table_key ORDER BY $this->users_table_key SEPARATOR ',') user_ids FROM $this->users_table WHERE $this->users_table_key IN ($user_ids)");
        return empty($row) ? '' : $row[0]->user_ids;
    }

    public
    function modifyUsersInConversations($conversation_ids, $user_ids)
    {
        if (is_array($conversation_ids)) $conversation_ids = implode(',', $conversation_ids);
        if (is_array($user_ids)) $user_ids = implode(',', $user_ids);

        // make sure we have no extra conversation ids where the user is no longer participating
        if (!empty($conversation_ids) && !empty($user_ids))
        {
            $conversations = $this->db->select(<<<SQL
SELECT GROUP_CONCAT(DISTINCT user_id ORDER BY user_id SEPARATOR ',') user_ids, conversation_id id FROM $this->conversation_users WHERE conversation_id IN ($conversation_ids) GROUP BY conversation_id
SQL
            );

            $this->db->beginTransaction();
            try
            {
                $user_ids = explode(',', $user_ids);

                foreach ($conversations as $conversation)
                {
                    $conversation_users = explode(',', $conversation->user_ids);
                    $addUsers = implode(',', array_diff($user_ids, $conversation_users));
                    $removeUsers = implode(',', array_diff($conversation_users, $user_ids));

                    if (!empty($addUsers)) $this->addUsersToConversations($conversation->id, $addUsers);
                    if (!empty($removeUsers)) $this->removeUsersFromConversations($conversation->id, $removeUsers);
                }

                $this->db->commit();
            }
            catch (Exception $e)
            {
                $this->db->rollBack();
                throw $e;
            }
        }
    }
}
