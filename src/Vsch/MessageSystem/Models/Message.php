<?php
namespace Vsch\MessageSystem\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * User: tzookb
 * Date: 3/21/14
 * Time: 6:20 PM
 */
class Message extends Model
{
    protected $table = 'messages';

    public
    function __construct($attributes = [])
    {
        $this->table = \Config::get('message-system::config.tablePrefix', '') . 'messages';
        parent::__construct($attributes);
    }
}
