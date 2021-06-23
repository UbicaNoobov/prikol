<?php

namespace PixelYourSite;
class GroupedEvent extends PYSEvent
{
    private $events = array();
    public function __construct($id, $type) {
        parent::__construct($id, $type);
    }

    /**
     * @param PYSEvent $event
     */
    public function addEvent($event) {
        $this->events[] = $event;
    }

    /**
     * @return PYSEvent[]
     */
    public function getEvents() {
        return $this->events;
    }

    public function removeChild($key) {
        unset($this->events[$key]);
    }


}