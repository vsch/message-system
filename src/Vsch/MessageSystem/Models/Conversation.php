<?php
namespace Vsch\MessageSystem\Models;

use Illuminate\Database\Eloquent\Model;
use Vsch\MessageSystem\Facade\Msg;

/**
 * Created by PhpStorm.
 * User: tzookb
 * Date: 3/21/14
 * Time: 6:20 PM
 */
class Conversation extends Model
{
    protected $table = 'conversations';

    /**
     * Conversation constructor.
     */
    public
    function __construct(array $attributes = array())
    {
        $this->table = \Config::get('message-system::config.table_prefix', '') . 'conversations';
        parent::__construct($attributes);
    }

    public static
    function boot()
    {
        parent::boot();

        /* @var $model Conversation */
        self::saved(function ($model)
        {
            if ($model->isDirty('user_ids'))
            {
                // update user id list in database
                Msg::modifyUsersInConversations($model->id, $model->user_ids);
            }
        });
    }
}
