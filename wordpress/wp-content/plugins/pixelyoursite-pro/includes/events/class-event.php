<?php
namespace PixelYourSite;
class EventTypes {
    static public $DYNAMIC = "dyn";
    static public $STATIC = "static";
    static public $TRIGGER = "trigger";
}

abstract class PYSEvent {
    protected $id;
    protected $type;
    public $args = null;
    /**
     * GroupedEvent constructor.
     * @param $id // unique id  use in js object like key
     * @param $type // can be static(fire when open page) or dynamic (fire when some event did)
     */
    public function __construct($id,$type){
        $this->id = $id;
        $this->type = $type;
    }

    function getId() {
        return $this->id;
    }

    function getType() {
        return $this->type;
    }
}