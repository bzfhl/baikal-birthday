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

class CalendarRootPlus extends \Sabre\CalDAV\CalendarRoot {
    function __construct($principalBackend,$caldavBackend) {

        parent::__construct($principalBackend,$caldavBackend);
        

    }
    function getChildForPrincipal(array $principal) {

        return new CalendarHomePlus($this->caldavBackend, $principal);

    }
} */
class CalendarRootPlus extends \Sabre\CalDAV\CalendarRoot {

	/**
	* This method returns a node for a principal.
	*
	* The passed array contains principal information, and is guaranteed to
	* at least contain a uri item. Other properties may or may not be
	* supplied by the authentication backend.
	*
	* @param array $principal
	* @return \Sabre\DAV\INode
	*/
    function getChildForPrincipal(array $principal) {

        return new CalendarHomePlus($this->caldavBackend, $principal);

    }

}
