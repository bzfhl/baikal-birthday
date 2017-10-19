<?php

namespace Baikal\Core;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component;
use Sabre\VObject\Reader;
/**
 * This class generates birthday calendars.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Dominik Tobschall (http://tobschall.de/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class BirthdayCalendarGenerator  extends \Sabre\VObject\BirthdayCalendarGenerator {


   /**
     * Parses the input data and returns a VCALENDAR.
     *
     * @return Component/VCalendar	function buildDateFromContact($cardData, $dateField='BDAY', $summarySymbol='*') {
     */
	function buildDateFromContact($cardData,$dateField='BDAY', $summarySymbol='*') {
		

		if (empty($cardData)) {
			return null;
		}
		$doc = $cardData;
		if (!isset($doc->{$dateField})) {
			return null;
		}
		$birthday = $doc->{$dateField};
		
		if (!(string)$birthday) {
			return null;
		}
		$title = str_replace('{name}',
			strtr((string)$doc->FN, array('\,' => ',', '\;' => ';')),
			'{name}'
		);
		$NOTE="";
		if (isset($doc->{'NOTE'})) {
			$NOTE=$doc->{'NOTE'};
		}
		try {
			$date = new \DateTime($birthday);
		} catch (Exception $e) {
			return null;
		}
		$vCals=[];
//		$vCal = new VCalendar();
//		$vCal->VERSION = '2.0';
		if((strpos($NOTE,'农历生日') !== false)||(strpos($NOTE,'阴历生日') !== false)){
			//$lunar = new Lunar();
			$year=(int)(date('Y')-1);
//			$year=(int)(date('Y'));			
			$YearEnd=(int)(date('Y')+1);			
			$month=(int)$date->format('m');
			$day=(int)$date->format('d');
			$lunar = new \Baikal\Core\Lunar();
			$summary=$title .'('.$date->format('Y').'年'.$lunar->getCapitalNum($month,true).$lunar->getCapitalNum($day).')';
			FOR ($year; $year<=$YearEnd; $year++){
				$vCal = new VCalendar();
				$vCal->VERSION = '2.0';				
	            $LeapMonth= (int)$lunar->getLeapMonth($year);
		        if ( $month > $LeapMonth &&  $LeapMonth != 0) {
				$Solardate = $lunar->convertLunarToSolar($year,$month+1,$day); 		        	
		        }else{
				$Solardate = $lunar->convertLunarToSolar($year,$month,$day); 		        	
		        }
				$date1 = new \DateTime($Solardate[0].'-'.$Solardate[1].'-'.$Solardate[2]);
				$vEvent = $vCal->createComponent('VEVENT');
				$vEvent->add('DTSTART');
				$vEvent->DTSTART->setDateTime($date1);
				$vEvent->DTSTART['VALUE'] = 'DATE';
				$vEvent->add('DTEND');
				$date1->add(new \DateInterval('P1D'));
				$vEvent->DTEND->setDateTime($date1);
				$vEvent->DTEND['VALUE'] = 'DATE';
				$vEvent->{'UID'} = $doc->UID;
//				$vEvent->{'RRULE'} = 'FREQ=YEARLY';
				$vEvent->{'SUMMARY'} = $summary;
				$vEvent->{'TRANSP'} = 'TRANSPARENT';
				$alarm = $vCal->createComponent('VALARM');
				$alarm->add($vCal->createProperty('TRIGGER', '-PT0M', ['VALUE' => 'DURATION']));
				$alarm->add($vCal->createProperty('ACTION', 'DISPLAY'));
				$alarm->add($vCal->createProperty('DESCRIPTION', $vEvent->{'SUMMARY'}));
				$vEvent->add($alarm);
				$vCal->add($vEvent);
//				$year++;
				$vCals[]=$vCal;
			} 
		}else{
			$vCal = new VCalendar();
			$vCal->VERSION = '2.0';	
			$summary = $title . ' (' . $date->format('Y-m-d') . ')';	
			$vEvent = $vCal->createComponent('VEVENT');
			$vEvent->add('DTSTART');
			$vEvent->DTSTART->setDateTime($date);
			$vEvent->DTSTART['VALUE'] = 'DATE';
//			$vEvent->add('DTEND');
//			$date->add(new \DateInterval('P1D'));
//			$vEvent->DTEND->setDateTime($date);
//			$vEvent->DTEND['VALUE'] = 'DATE';
			$vEvent->{'UID'} = $doc->UID;
			$vEvent->{'RRULE'} = 'FREQ=YEARLY';
			$vEvent->{'DURATION'} = 'P1D';
			$vEvent->{'SUMMARY'} = $summary;
			$vEvent->{'TRANSP'} = 'TRANSPARENT';
			$alarm = $vCal->createComponent('VALARM');
			$alarm->add($vCal->createProperty('TRIGGER', '-PT0M', ['VALUE' => 'DURATION']));
			$alarm->add($vCal->createProperty('ACTION', 'DISPLAY'));
			$alarm->add($vCal->createProperty('DESCRIPTION', $vEvent->{'SUMMARY'}));
			$vEvent->add($alarm);
			$vCal->add($vEvent);
			$vCals[]=$vCal;
		}
		
		return $vCals;
	}
    function getResult() {

        $calendar = new VCalendar();
        foreach ($this->objects as $object) {
        	$calendar = $this->buildDateFromContact($object);
        	}
        return $calendar;
    }

}
