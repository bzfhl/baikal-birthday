<?php
#################################################################
#  Copyright notice
#
#  (c) 2013 Jérôme Schneider <mail@jeromeschneider.fr>
#  All rights reserved
#
#  http://baikal-server.com
#
#  This script is part of the Baïkal Server project. The Baïkal
#  Server project is free software; you can redistribute it
#  and/or modify it under the terms of the GNU General Public
#  License as published by the Free Software Foundation; either
#  version 2 of the License, or (at your option) any later version.
#
#  The GNU General Public License can be found at
#  http://www.gnu.org/copyleft/gpl.html.
#
#  This script is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  This copyright notice MUST APPEAR in all copies of the script!
#################################################################


namespace Baikal\Core;

use \Sabre\DAV;
use \Sabre\DAV\PropPatch;
use \Sabre\DAVACL;
use \Sabre\VObject;
use \Sabre\CalDAV;
use \Sabre\CardDAV;
use \Sabre\CardDAV\Backend;

/**
 * The Baikal Server
 *
 * This class sets up the underlying Sabre\DAV\Server object.
 *
 * @copyright Copyright (C) Jérôme Schneider <mail@jeromeschneider.fr>
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ GPLv2
 */
class PDOPlus extends \Sabre\CalDAV\Backend\PDO  implements  \Sabre\CalDAV\Backend\SharingSupport {
    public $addressBooksTableName = 'addressbooks';
    /**
     * The PDO table name used to store cards
     */
    public $cardsTableName = 'cards';
    public $addressBookChangesTableName = 'addressbookchanges';
    /**
     * Creates the server object.
     *
     * @param bool $enableCalDAV
     * @param bool $enableCardDAV
     * @param string $authType
     * @param string $authRealm
     * @param PDO $pdo
     * @param string $baseUri
     */
    function __construct($pdo) {

        parent::__construct($pdo);

    }
     function getCalendarsForUser($principalUri) {

			$calendars = [];
            $calendars = parent::getCalendarsForUser($principalUri);
			$stmt = $this->pdo->prepare('SELECT  max(synctoken) AS synctoken  FROM ' . $this->addressBooksTableName . ' WHERE principaluri = ? ');
     	   	$stmt->execute([$principalUri]);            
     	   	if($row = $stmt->fetch(\PDO::FETCH_ASSOC)){
	            $calendar = [
	                'id'                                                                        => 'contact_birthdays',
	                'uri'                                                                       => 'contact_birthdays',
	                'principaluri'                                                              => $principalUri,
	                '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
	                '{http://sabredav.org/ns}sync-token'                                        => $row['synctoken'],
	                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet(array('VEVENT')),
	                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp( 'opaque'),
	                '{DAV:}displayname'                                                         => '生日日历',
	                '{urn:ietf:params:xml:ns:caldav}calendar-description'                       => '生日日历测试',
	                '{urn:ietf:params:xml:ns:caldav}calendar-timezone'                          => '',
	                '{http://apple.com/ns/ical/}calendar-order'                                 => '1',
	                '{http://apple.com/ns/ical/}calendar-color'                                 => '#FF0000',
	                '{http://sabredav.org/ns}read-only'                                         => true,
	            ]; 
	          $calendars[] = $calendar;     	   		
     	   	}
        return $calendars;

    }

	function getCalendarObjects($calendarId,$principalUri="principals/bzfhl") {
		$result = [];
		if($calendarId === 'contact_birthdays') {
			$stmt = $this->pdo->prepare('SELECT cards.id, cards.uri, cards.lastmodified, cards.etag, cards.addressbookid, cards.carddata FROM ' . $this->cardsTableName . ' AS cards LEFT JOIN ' . $this->addressBooksTableName . ' AS addressBooks ON cards.addressbookid = addressBooks.id  WHERE (carddata like "%BDAY%") AND addressBooks.principaluri = ?');
   			$stmt->execute([$principalUri]);
   	   		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
   		    	$generator = new BirthdayCalendarGenerator($row['carddata']);
   		    	$vCals = $generator->getResult(); 
   		    	if (empty($vCals)) continue; 
   		    	$calid=0;
   		    	foreach ($vCals as $calendardata) {
   			    if (!$calendardata->{'VEVENT'}) continue; 
   			    $result[] = [
    				'id'           => $row['id'],
    				'uri'          => $calid.'-'.substr_replace($row['uri'],'ics',-3),
    				'lastmodified' => $row['lastmodified'],
    				'etag'         => '"' . $row['etag'] . '"',
    				'calendarid'   => 'contact_birthdays',
    				'size'         => (int)strlen($calendardata->serialize()),
    				'component'    => strtolower('VEVENT'),
    			];
    			$calid++;
    			}
   		 	}
		} else {
		$result=parent::getCalendarObjects($calendarId);
		}
		return $result;	
	} 

	function getCalendarObject($calendarId, $objectUri, $principalUri = "principals/bzfhl") {
		
		$result = [];
		if($calendarId === 'contact_birthdays') {
			$calid=(int)substr( $objectUri, 0, 1 );
			$cardUri=substr($objectUri,2);	
			$cardUri=substr_replace($cardUri, 'vcf',-3);	
			$stmt = $this->pdo->prepare('SELECT cards.id, cards.uri, cards.lastmodified, cards.etag, cards.addressbookid, cards.carddata FROM ' . $this->cardsTableName . ' AS cards LEFT JOIN ' . $this->addressBooksTableName . ' AS addressBooks ON cards.addressbookid = addressBooks.id  WHERE addressBooks.principaluri = ? AND  cards.uri = ?');
	        $stmt->execute([$principalUri, $cardUri]);		
	        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
	        $generator = new BirthdayCalendarGenerator($row['carddata']);
	    	$calendardata = $generator->getResult()[$calid]; 
		    if (!$calendardata->{'VEVENT'})  return  null; 
	        return [
	            'id'            => 0,
	            'uri'           => $calid.'-'.substr_replace($row['uri'],'ics',-3),
	            'lastmodified'  => $row['lastmodified'],
	            'etag'          => '"' . $row['etag'] . '"',
	            'calendarid'    => 'contact_birthdays',
	           	'size'          => (int)strlen($calendardata->serialize()),
	          	'calendardata'  => $calendardata->serialize(), 
	            'component'     => strtolower('VEVENT'),
	         ];  
			} else {
			$result=parent::getCalendarObject($calendarId, $objectUri);
			}
		return $result;		
	}
   function getMultipleCalendarObjects($calendarId, array $uris,$principalUri="principals/bzfhl") {
		$result = [];
		if($calendarId === 'contact_birthdays') {
		        return array_map(function($uri) use ($calendarId, $principalUri) {
		            return $this->getCalendarObject($calendarId, $uri, $principalUri);
		        }, $uris);
			} else {
				$result=parent::getMultipleCalendarObjects($calendarId, $uris);
			}
		return $result;		
    }

    function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null,$principalUri = "principals/bzfhl") {
       $result = [];    
    	if($calendarId === 'contact_birthdays') {
    		        // Current synctoken
        $stmt = $this->pdo->prepare('SELECT synctoken , addressbookid FROM ' . $this->addressBooksTableName . ' WHERE principaluri = ?');
        $stmt->execute([$principalUri]);
        $currentToken = $stmt->fetchColumn(0);
        $addressbookid = $stmt->fetchColumn(1);
        if (is_null($currentToken)) return null;

        $result = [
            'syncToken' => $currentToken,
            'added'     => [],
            'modified'  => [],
            'deleted'   => [],
        ];

        if ($syncToken) {

            $query = "SELECT uri, operation FROM " . $this->addressBookChangesTableName . " WHERE synctoken >= ? AND synctoken < ? AND addressbookid = ? ORDER BY synctoken";
            if ($limit > 0) $query .= " LIMIT " . (int)$limit;

            // Fetching all changes
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$syncToken, $currentToken, $addressbookid]);

            $changes = [];

            // This loop ensures that any duplicates are overwritten, only the
            // last change on a node is relevant.
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $changes[$row['uri']] = $row['operation'];

            }

            foreach ($changes as $uri => $operation) {

                switch ($operation) {
                    case 1:
                        $result['added'][] = $uri;
                        break;
                    case 2:
                        $result['modified'][] = $uri;
                        break;
                    case 3:
                        $result['deleted'][] = $uri;
                        break;
                }

            }
        } else {
            // No synctoken supplied, this is the initial sync.
            $query = "SELECT uri FROM " . $this->cardsTableName . " WHERE  (carddata like "%BDAY%") AND addressbookid = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressbookid]);

            $result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
    	} else {
    		$result=parent::getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit);
    	}
        return $result;
	}
/*	
    function getCalendarObjectByUID($principalUri, $uid) {
    	if($calendarId === 'contact_birthdays') {
            return 'contact_birthdays/' . $uid.'.ics';
    	} else {
			return parent::getCalendarObjectByUID($principalUri, $uid);
	}
    }

*/

    function calendarQuery($calendarId, array $filters,$principalUri="principals/bzfhl") {
        $result = [];    	
		if($calendarId === 'contact_birthdays') {    
		        $objects = $this->getCalendarObjects($calendarId,$principalUri);
		        
				if(is_array($objects)){
				        foreach($objects as $object) {
				            if ($this->validateFilterForObject($object, $filters)) {
				                $result[] = $object['uri'];
				            }
				        }
				}
			} else {
				$result=parent::calendarQuery($calendarId, $filters);
	    }
		return $result;	    
	}
	function RecordsFile(array $data ){

        $Separator=",";
        // public $dir = ;
        //$dir = './upload/user_excel';//本地测试报错，权限不足，跟系统环境有关；相关解决可谷歌
         $filename = date('Ymd').'.txt';
       $dir =dirname(__file__);
        //$filename=date('Ymd').'.txt';;
        if( !$dir || !$filename ||!is_array($data) ) return false;
        if(!is_dir($dir)) mkdir($dir);
       // $content =implode( $Separator,$data );
        if( is_array( $data ) || is_object( $data ) ){
            $content =( print_r( $data, true ) );
        } else {
            $content =( $data );
        }
       // $content =serialize($data);
       // $content=str_replace(PHP_EOL, '', $content);
        $content = preg_replace('/\s(?=\s)/', '', $content);
        $result = file_put_contents( $dir.'/'.$filename,(date('Y-m-d h:i:s',time())).' '.$content."\r\n",FILE_APPEND | LOCK_EX );
        return $result;
	}
	
	public function updateShares($shareable, $add, $remove) {
		foreach($add as $element) {
			$this->shareWith($shareable, $element);
		}
		foreach($remove as $element) {
			$this->unshare($shareable, $element);
		}
	}
	/**
	 * @param IShareable $shareable
	 * @param string $element
	 */
	private function shareWith($shareable, $element) {
//		$user = $element['href'];
//		$parts = explode(':', $user, 2);
//		if ($parts[0] !== 'principal') {
//			return;
//		}
//
//		// don't share with owner
//		if ($shareable->getOwner() === $parts[1]) {
//			return;
//		}
//
//		// remove the share if it already exists
//		$this->unshare($shareable, $element['href']);
//		$access = self::ACCESS_READ;
//		if (isset($element['readOnly'])) {
//			$access = $element['readOnly'] ? self::ACCESS_READ : self::ACCESS_READ_WRITE;
//		}
//
//		$query = $this->db->getQueryBuilder();
//		$query->insert('dav_shares')
//			->values([
//				'principaluri' => $query->createNamedParameter($parts[1]),
//				'type' => $query->createNamedParameter($this->resourceType),
//				'access' => $query->createNamedParameter($access),
//				'resourceid' => $query->createNamedParameter($shareable->getResourceId())
//			]);
//		$query->execute();
	}
	private function unshare($shareable, $element) {
//		$parts = explode(':', $element, 2);
//		if ($parts[0] !== 'principal') {
//			return;
//		}
//
//		// don't share with owner
//		if ($shareable->getOwner() === $parts[1]) {
//			return;
//		}
//
//		$query = $this->db->getQueryBuilder();
//		$query->delete('dav_shares')
//			->where($query->expr()->eq('resourceid', $query->createNamedParameter($shareable->getResourceId())))
//			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
//			->andWhere($query->expr()->eq('principaluri', $query->createNamedParameter($parts[1])))
//		;
//		$query->execute();
	}
	
	/**
	 * @param int $resourceId
	 * @return array
	 */
	public function getShares($resourceId) {
//		$query = $this->db->getQueryBuilder();
//		$result = $query->select(['principaluri', 'access'])
//			->from('dav_shares')
//			->where($query->expr()->eq('resourceid', $query->createNamedParameter($resourceId)))
//			->andWhere($query->expr()->eq('type', $query->createNamedParameter($this->resourceType)))
//			->execute();

		$shares = [];
//		while($row = $result->fetch()) {
//			$p = $this->principalBackend->getPrincipalByPath($row['principaluri']);
//			$shares[]= [
//				'href' => "principal:${row['principaluri']}",
//				'commonName' => isset($p['{DAV:}displayname']) ? $p['{DAV:}displayname'] : '',
//				'status' => 1,
//				'readOnly' => ($row['access'] == self::ACCESS_READ),
//				'{http://owncloud.org/ns}principal' => $row['principaluri'],
//				'{http://owncloud.org/ns}group-share' => is_null($p)
//			];
//		}

		return $shares;
	}

    /**
     * This method is called when a user replied to a request to share.
     *
     * If the user chose to accept the share, this method should return the
     * newly created calendar url.
     *
     * @param string href The sharee who is replying (often a mailto: address)
     * @param int status One of the SharingPlugin::STATUS_* constants
     * @param string $calendarUri The url to the calendar thats being shared
     * @param string $inReplyTo The unique id this message is a response to
     * @param string $summary A description of the reply
     * @return null|string
     */
    function shareReply($href, $status, $calendarUri, $inReplyTo, $summary = null){
    
    return null;
    }
	/**
	 * @param boolean $value
	 * @param \OCA\DAV\CalDAV\Calendar $calendar
	 * @return string|null
	 */
	public function setPublishStatus($calendarId, $value) {
//		$query = $this->db->getQueryBuilder();
//		if ($value) {
//			$publicUri = $this->random->generate(16, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_DIGITS);
//			$query->insert('dav_shares')
//				->values([
//					'principaluri' => $query->createNamedParameter($calendar->getPrincipalURI()),
//					'type' => $query->createNamedParameter('calendar'),
//					'access' => $query->createNamedParameter(self::ACCESS_PUBLIC),
//					'resourceid' => $query->createNamedParameter($calendar->getResourceId()),
//					'publicuri' => $query->createNamedParameter($publicUri)
//				]);
//			$query->execute();
//			return $publicUri;
//		}
//		$query->delete('dav_shares')
//			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendar->getResourceId())))
//			->andWhere($query->expr()->eq('access', $query->createNamedParameter(self::ACCESS_PUBLIC)));
//		$query->execute();
		return null;
	}

}
