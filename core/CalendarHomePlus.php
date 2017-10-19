<?php

#namespace Baikal\Core\CALPlus;
namespace Baikal\Core;

/**
 * Calendars collection
 *
 * This object is responsible for generating a list of calendar-homes for each
 * user.
 *
 * This is the top-most node for the calendars tree. In most servers this class
 * represents the "/calendars" path.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 class CalendarRoot extends \Sabre\DAVACL\AbstractPrincipalCollection {
 */
class CalendarHomePlus extends \Sabre\CalDAV\CalendarHome {


    /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    function getChild($name) {

        // Special nodes
        if ($name === 'inbox' && $this->caldavBackend instanceof Backend\SchedulingSupport) {
            return new Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
        }
        if ($name === 'outbox' && $this->caldavBackend instanceof Backend\SchedulingSupport) {
            return new Schedule\Outbox($this->principalInfo['uri']);
        }
        if ($name === 'notifications' && $this->caldavBackend instanceof Backend\NotificationSupport) {
            return new Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // Calendars
        foreach ($this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']) as $calendar) {
            if ($calendar['uri'] === $name) {
                if ($this->caldavBackend instanceof Backend\SharingSupport) {
                    if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
                        return new SharedCalendarPlus($this->caldavBackend, $calendar);
                    } else {
                        return new ShareableCalendarPlus($this->caldavBackend, $calendar);
                    }
                } else {
                    return new CalendarPlus($this->caldavBackend, $calendar);
                }
            }
        }

        if ($this->caldavBackend instanceof Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                if ($subscription['uri'] === $name) {
                    return new Subscriptions\Subscription($this->caldavBackend, $subscription);
                }
            }

        }

        throw new NotFound('Node with name \'' . $name . '\' could not be found');

    }


    /**
     * Returns a list of calendars
     *
     * @return array
     */
    function getChildren() {

        $calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
        $objs = [];
        foreach ($calendars as $calendar) {
            if ($this->caldavBackend instanceof Backend\SharingSupport) {
                if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
                    $objs[] = new SharedCalendar($this->caldavBackend, $calendar);
                } else {
                    $objs[] = new ShareableCalendar($this->caldavBackend, $calendar);
                }
            } else {
                $objs[] = new CalendarPlus($this->caldavBackend, $calendar);
            }
        }

        if ($this->caldavBackend instanceof Backend\SchedulingSupport) {
            $objs[] = new Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
            $objs[] = new Schedule\Outbox($this->principalInfo['uri']);
        }

        // We're adding a notifications node, if it's supported by the backend.
        if ($this->caldavBackend instanceof Backend\NotificationSupport) {
            $objs[] = new Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // If the backend supports subscriptions, we'll add those as well,
        if ($this->caldavBackend instanceof Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                $objs[] = new Subscriptions\Subscription($this->caldavBackend, $subscription);
            }
        }

        return $objs;

    }

}