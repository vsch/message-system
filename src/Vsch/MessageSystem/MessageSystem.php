<?php
namespace Vsch\MessageSystem;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Vsch\MessageSystem\Models\Conversation;

class MessageSystem
{
    protected $usersTable;
    protected $usersTableKey;
    protected $tablePrefix;

    /**
     * @var MessageRepository
     */
    protected $repository;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    public
    function __construct(MessageRepository $repository, Dispatcher $dispatcher)
    {
        $this->repository = $repository;
        $this->dispatcher = $dispatcher;
    }

    public
    function markMessageAs($msgId, $userId, $status)
    {
        $this->repository->markMessageAs($msgId, $userId, $status);
    }

    public
    function markMessageAsRead($msgId, $userId)
    {
        $this->repository->markMessageAsRead($msgId, $userId);
    }

    public
    function markMessageAsUnread($msgId, $userId)
    {
        $this->repository->markMessageAsUnread($msgId, $userId);
    }

    public
    function markMessageAsDeleted($msgId, $userId)
    {
        $this->repository->markMessageAsDeleted($msgId, $userId);
    }

    public
    function markMessageAsArchived($msgId, $userId)
    {
        $this->repository->markMessageAsArchived($msgId, $userId);
    }

    public
    function getUserConversations($user_id)
    {
        $messages = $this->repository->getConversations($user_id);
        $conversations = [];
        foreach ($messages as $message)
        {
            if (!array_key_exists($message->conversation_id, $conversations))
            {
                $conv = new \stdClass();
                $conv->id = $message->conversation_id;
                $conv->title = $message->conversation_title;
                $conv->user_ids = $message->conversation_user_ids;
                $conv->participants = $message->conversation_participants;
                $conv->unread = 0;
                $conv->self = 0;
                $conv->messages = [];

                $conversations[$conv->id] = $conv;
            }

            if ($message->content !== null && $message->content !== '')
            {
                $conv = $conversations[$message->conversation_id];

                $conv->messages[] = $message;
                if ($message->self) $conv->self++;
                elseif ($message->status === MessageRepository::UNREAD) $conv->unread++;
            }
        }

        return $conversations;
    }

    /**
     * @param      $conv_id
     * @param      $user_id
     * @param bool $newToOld
     *
     * @return \Vsch\MessageSystem\Models\Conversation
     */
    public
    function getConversationMessages($conv_id, $user_id, $newToOld = true)
    {

        $results = $this->repository->getConversationMessages($conv_id, $user_id, $newToOld);
        return $results;
    }

    /**
     * @param mixed $user_ids array or comma separated list of ids of users
     *
     * @return array | null               array of conversation rows or null if none where the listed users are the exact participants
     */
    public
    function getConversationsBetweenUsers($user_ids)
    {
        $conversation_ids = $this->repository->getConversationsBetweenUsers($user_ids);
        return $conversation_ids;
    }

    public
    function addMessageToConversation($conv_id, $user_id, $content, $class = null)
    {
        $message = $this->repository->addMessageToConversation($conv_id, $user_id, $content, $class);

        if ($message) $this->dispatcher->fire('message.sent', [$message]);
        return $message;
    }

    /**
     * @param array $users_ids
     *
     * @return Conversation
     */
    public
    function createConversation($users_ids, $title = null, $class = null)
    {
        return $this->repository->createConversation($users_ids, $title, $class);
    }

    public
    function sendMessageBetweenUsers($senderId, $receiverIds, $content, $title = null)
    {
        //get conversation between users
        if (is_array($receiverIds)) $receiverIds = implode(',', $receiverIds);
        $receiverIds = "$senderId,$receiverIds";
        $conversation_ids = $this->repository->getConversationsBetweenUsers($receiverIds);

        if (empty($conversation_ids))
        {
            //if conversation doesn't exist, create it
            $conversation = $this->repository->createConversation($receiverIds, $title);
            $conversation_id = $conversation->id;
        }
        else
        {
            // take the first conversation, which is the latest
            $conversation_id = explode(',', $conversation_ids, 2)[0];
        }

        //add message to new conversation
        $this->repository->addMessageToConversation($conversation_id, $senderId, $content);
    }

    public
    function markAllMessagesInConversationRead($conv_id, $user_id)
    {
        $this->repository->markAllMessagesInConversationAs($conv_id, $user_id, MessageRepository::READ, MessageRepository::UNREAD);
    }

    public
    function markAllMessagesInConversationUnread($conv_id, $user_id)
    {
        $this->repository->markAllMessagesInConversationAs($conv_id, $user_id, MessageRepository::UNREAD, MessageRepository::READ);
    }

    public
    function deleteConversationMessages($conv_id, $user_id)
    {
        $this->repository->markAllMessagesInConversationAs($conv_id, $user_id, MessageRepository::DELETED);
    }

    public
    function isUserInConversation($conv_id, $user_id)
    {
        return $this->repository->isUserInConversation($conv_id, $user_id);
    }

    public
    function getUsersInConversation($conv_id)
    {
        return $this->repository->getUsersInConversation($conv_id);
    }

    public
    function getUnreadMessageCount($user_id)
    {
        return $this->repository->getUnreadMessageCount($user_id);
    }

    public
    function removeUsersFromConversations($conversation_ids, $user_ids)
    {
        $this->repository->removeUsersFromConversations($conversation_ids, $user_ids);
    }
}
