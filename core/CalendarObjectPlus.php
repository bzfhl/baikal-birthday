<?php
namespace Baikal\Core;
/**
 * ownCloud - OC_Connector_Sabre_CalDAV_CalendarObject
 *
 * @author Thomas Tanghus
 * @copyright 2012 Thomas Tanghus (thomas@tanghus.net)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * This class overrides \Sabre\CalDAV\CalendarObject::getACL()
 * to return read/write permissions based on user and shared state.
*/
class CalendarObjectPlus extends \Sabre\CalDAV\CalendarObject {

	/**
	* Returns a list of ACE's for this node.
	*
	* Each ACE has the following properties:
	*   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	*     currently the only supported privileges
	*   * 'principal', a url to the principal who owns the node
	*   * 'protected' (optional), indicating that this ACE is not allowed to
	*      be updated.
	*
	* @return array
	*/
    function getACL() {

        // An alternative acl may be specified in the object data.
        if (isset($this->objectData['acl'])) {
            return $this->objectData['acl'];
        }
         // The default ACL       
        $acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->calendarInfo['principaluri'] ,
                'protected' => true,
            ],
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->calendarInfo['principaluri']  . '/calendar-proxy-write',
                'protected' => true,
            ],
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->calendarInfo['principaluri']  . '/calendar-proxy-read',
                'protected' => true,
            ],
        ];

        if($this->calendarInfo['id'] !== 'contact_birthdays'){
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->calendarInfo['principaluri'],
                'protected' => true,
            ];
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->calendarInfo['principaluri']. '/calendar-proxy-write',
                'protected' => true,
            ];
        }
        return $acl;
    }

    function get() {

        // Pre-populating the 'calendardata' is optional, if we don't have it
        // already we fetch it from the backend.
        if (!isset($this->objectData['calendardata'])) {
            $this->objectData = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $this->objectData['uri'],$this->getOwner());
        }
        return $this->objectData['calendardata'];

    }
}
