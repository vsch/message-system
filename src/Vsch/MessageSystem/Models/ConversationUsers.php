<?php
namespace Vsch\MessageSystem\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * User: tzookb
 * Date: 3/21/14
 * Time: 6:20 PM
 */

class ConversationUsers  extends Model {
    protected $table = 'conversation_users';
    public $timestamps = false;

    public
    function __construct(array $attributes = array())
    {
        $this->table = \Config::get('message-system::config.tablePrefix', '') . 'conversation_users';
        parent::__construct($attributes);
    }
} 
