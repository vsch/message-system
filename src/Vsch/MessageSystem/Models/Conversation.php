<?php
namespace Vsch\MessageSystem\Models;

use Illuminate\Database\Eloquent\Model;

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
        $this->table = \Config::get('message-system::config.tablePrefix', '') . 'conversations';
        parent::__construct($attributes);
    }
}
