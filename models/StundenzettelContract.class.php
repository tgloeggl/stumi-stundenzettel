<?php

/**
 * @author  <asudau@uos.de>
 *
 * @property varchar       $id
 * @property varchar       $user_id
 * @property varchar       $inst_id
 * @property int           $contract_hours
 * @property varchar       $supervisor
 * @property int           $contract_begin
 * @property int           $contract_end
 * @property int           $last_year_vacation_remaining
 * @property int           $begin_digital_recording_month
 * @property int           $begin_digital_recording_year
 * @property int           $begin_balance
 * @property int           $begin_vacation_claimed 
 */

class StundenzettelContract extends \SimpleORMap
{
    
    private static $dezimal_to_minute = array(
        '01' => '00', '51' => '30',
        '02' => '01', '52' => '31',
        '03' => '02', '53' => '32',
        '04' => '02', '54' => '32',
        '05' => '03', '55' => '33',
        '06' => '03', '56' => '33',
        '07' => '04', '57' => '34',
        '08' => '05', '58' => '35',
        '09' => '05', '59' => '35',
        '10' => '06', '60' => '36',
        '11' => '06', '61' => '36',
        '12' => '07', '62' => '37',
        '13' => '08', '63' => '38',
        '14' => '08', '64' => '38',
        '15' => '09', '65' => '39',
        '16' => '09', '66' => '39',
        '17' => '10', '67' => '40',
        '18' => '11', '68' => '41',
        '19' => '11', '69' => '41',
        '20' => '12', '70' => '42',
        '21' => '12', '71' => '42',
        '22' => '13', '72' => '43',
        '23' => '14', '73' => '44',
        '24' => '14', '74' => '44',
        '25' => '15', '75' => '45',
        '26' => '15', '76' => '45',
        '27' => '16', '77' => '46',
        '28' => '17', '78' => '47',
        '29' => '17', '79' => '47',
        '30' => '18', '80' => '48',
        '31' => '18', '81' => '48',
        '32' => '19', '82' => '49',
        '33' => '20', '83' => '50',
        '34' => '20', '84' => '50',
        '35' => '21', '85' => '51',
        '36' => '21', '86' => '51',
        '37' => '22', '87' => '52',
        '38' => '23', '88' => '53',
        '39' => '23', '89' => '53',
        '40' => '24', '90' => '54',
        '41' => '24', '91' => '54',
        '42' => '25', '92' => '55',
        '43' => '26', '93' => '56',
        '44' => '26', '94' => '56',
        '45' => '27', '95' => '57',
        '46' => '27', '96' => '57',
        '47' => '28', '97' => '58',
        '48' => '29', '98' => '59',
        '49' => '29', '99' => '59',
        '50' => '30'
        );
    
    private static $staus_array = array(
        'true_icon_role' => Icon::ROLE_STATUS_GREEN,
        'false_icon_role' => Icon::ROLE_NAVIGATION,
        'waiting_icon_role' => Icon::ROLE_SORT,
        'overdue_icon_role' => Icon::ROLE_STATUS_RED,
        'finished' => array(
            'icon' => 'radiobutton-checked',
            'true_tooltip' => 'Digitaler Stundenzettel eingereicht',
            'false_tooltip' => 'Digitaler Stundenzettel noch nicht eingereicht',
            'overdue_tooltip' => 'Einreichen des digitalen Stundenzettels überfällig'
            ),
        'approved' => array(
            'icon' => 'accept',
            'true_tooltip' => 'Digitaler Stundenzettel durch verantwortliche/n Mitarbeiter/in freigegeben',
            'false_tooltip' => 'Digitaler Stundenzettel noch nicht durch verantwortliche/n Mitarbeiter/in geprüft und freigegeben',
            'waiting_tooltip' => 'Warten auf Freigabe durch verantwortliche/n Mitarbeiter/in',
            'overdue_tooltip' => 'Prüfung und Freigabe durch verantwortliche/n Mitarbeiter/in überfällig'
            ),
        'received' => array(
            'icon' => 'inbox',
            'true_tooltip' => 'Papierausdruck liegt unterschrieben im Sekretariat vor',
            'false_tooltip' => 'Papierausdruck liegt noch nicht im Sekretariat vor',
            'waiting_tooltip' => 'Warten auf Eintreffen das ausgedruckten Stundenzettels im Sekretariat',
            'overdue_tooltip' => 'Eintreffen des Papierausdrucks im Sekretariat überfällig'
            ),
        'complete' => array(
            'icon' => 'lock-locked',
            'true_tooltip' => 'Vorgang abgeschlossen',
            'false_tooltip' => 'Vorgang offen',
            'waiting_tooltip' => 'Weiterleiten des Stundenzettel ans Personaldezernat',
            'overdue_tooltip' => 'Vorgang überfällig',
            ),
        );
    
    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->registerCallback('before_store', 'before_store');
    }
    
    protected static function configure($config = array())
    {
        $config['db_table'] = 'stundenzettel_contracts';
        
        $config['belongs_to']['stumi'] = [
            'class_name'  => 'User',
            'foreign_key' => 'user_id',];
        
        $config['additional_fields']['default_workday_time']['get'] = function ($item) {
            $workday_hours = floor($item->default_workday_time_in_minutes / 60);
            $workday_minutes = $item->default_workday_time_in_minutes % 60;
            return sprintf("%02s", $workday_hours) . ':' . self::$dezimal_to_minute[$item->default_workday_minutes_dezimal];
            
        };
        
        $config['additional_fields']['default_workday_time_in_minutes']['get'] = function ($item) {
            $workday_minutes_total = round($item->contract_hours /4.348 / 5 * 60);//* 2.75;
            return $workday_minutes_total;
            
        };
        
        $config['additional_fields']['default_workday_minutes_dezimal']['get'] = function ($item) {
            $workday_total_dezimal = round($item->contract_hours /4.348 / 5 , 2);//* 2.75;
            $workday_minutes_dezimal = explode('.', strval($workday_total_dezimal))[1];
            return $workday_minutes_dezimal;    
        };
        
        parent::configure($config);
    }
    
    //Laufzeitüberschneidungen mit bestehenden Verträgen der Einrichtung prüfen
    protected function before_store()
    {
        $contracts = self::findBySQL('user_id = ? AND inst_id = ?', [$this->user_id, $this->inst_id]);
        foreach ($contracts as $contract){
            if ($contract->id != $this->id){
                if ( (($this->contract_begin < $contract->contract_begin) && ($contract->contract_begin < $this->contract_end)) ||  
                     (($this->contract_begin < $contract->contract_end) && ($contract->contract_end < $this->contract_end)) ||
                     (($contract->contract_begin < $this->contract_begin) && ($contract->contract_end > $this->contract_end)) ) {
                    throw new Exception(_('Laufzeitüberschneidung mit bestehendem Vertrag'));
                }
            }
        }
    }
    
    function can_read($user){
        if ($this->user_id == $user->user_id || $this->supervisor == $user->user_id) {
            return true;
        }   
    }
    
    static function getCurrentContractId($user_id)
    {
        $contracts = self::findByUser_id($user_id);
        $contract_id = '';
        foreach ($contracts as $contract) {
            if (intval($contract->contract_begin) < time() && intval($contract->contract_end) > time()) {
                $contract_id = $contract->id;
            }
        }
        return $contract_id;
    }
    
    static function getSomeContractId($user_id)
    {
        $contracts = self::findByUser_id($user_id);
        return $contracts[0]->id;
    }
    
    static function getContractsByMonth($month, $year)
    {
        $begin_lastmonth = strtotime(date("y-m",strtotime("-1 month")) . '-01');
        $end_nextmonth = strtotime(date("y-m",strtotime("+1 month")) . '-28');
        $month_begin = strtotime($year . '-' . $month  . '-01' );
        $month_end = strtotime($year . '-' . $month  . '-28' );
        //$contracts = self::findBySQL('contract_begin < ? AND contract_end > ?', [$end_nextmonth, $begin_lastmonth]);
        $all_contracts = self::findBySQL('contract_begin < ? AND contract_end > ?', [$month_end, $month_begin]);
        
        if (Stundenzettel::hasStumiAdminrole() || $GLOBALS['perm']->have_perm('admin')){
            return $all_contracts;
        } else {
            foreach ($all_contracts as $contract){
                if ($contract->user_id == User::findCurrent()->user_id || $contract->supervisor == User::findCurrent()->user_id){
                    $contracts[] = $contract;
                }
            }
        }
        
        return $contracts;
    }
    
    //TODO Institutsbezogen
     static function getUserContractsByMonth($user_id, $month, $year)
    {
        $begin_lastmonth = strtotime(date("y-m",strtotime("-1 month")) . '-01');
        $end_nextmonth = strtotime(date("y-m",strtotime("+1 month")) . '-28');
        $month_begin = strtotime($year . '-' . $month  . '-01' );
        $month_end = strtotime($year . '-' . $month  . '-28' );
        //$contracts = self::findBySQL('contract_begin < ? AND contract_end > ?', [$end_nextmonth, $begin_lastmonth]);
        $contract = self::findOneBySQL('user_id = ? AND contract_begin < ? AND contract_end > ?', [$user_id, $month_end, $month_begin]);
        return $contract;
    }
    
    static function getStaus_array()
    {
        return self::$staus_array;
    }
    
    function getContractDuration()
    {
        $begin_date = new \DateTime();
        $begin_date->setTimestamp($this->contract_begin);
        $end_date = new \DateTime();
        $end_date->setTimestamp($this->contract_end);
        
        $interval = date_diff($begin_date, $end_date);
        $month = $interval->y * 12 + $interval->m;
        if ($interval->d >15){
            $month++;   //php date_diff tut sich hier leider schwer 
                        //1.10.2020 bis 31.10.2020 ist ein Monat 
                        //aber 1.11.2020-30.11.2020 is 0 Monate und 29 tage
        }
        return $month;
    }
    
    function monthPartOfContract($month, $year)
    {
        return (intval($this->contract_begin) < strtotime($year . '-' . $month . '-28')) && (strtotime($year . '-' . $month . '-01') < intval($this->contract_end)); 
    }
    
    function monthWithVacationEntitlement($year)
    {
        $contract_month_in_year = 12;
        if (($this->contract_end < strtotime('01.07.' . $year)) || ($this->contract_begin > strtotime('01.07.' . $year))) {
            for ($i = 1; $i<= 12; $i++) {
                if (!$this->monthPartOfContract($i, $year)){
                    $contract_month_in_year --;
                }
            }
        }
        return $contract_month_in_year;
    }
    
    //unterscheidet sich von monthPartOfContract, weil der offizielle Aufzeichnungsbeginn vom Vertragsbeginn abweichen kann
    function monthWithinRecordingTime($month, $year)
    {
        if ($this->monthPartOfContract($month, $year)) {
            if ($this->begin_digital_recording_month && $this->begin_digital_recording_year) {    
                if (($year == $this->begin_digital_recording_year && $month < $this->begin_digital_recording_month) ||
                      $year < $this->begin_digital_recording_year  ){
                    return false; //im Vertragszeitraum und vor konfiguriertem Aufzeichnungbeginn
                } else return true; //im Vertragszeitraum und nach konfiguriertem Aufzeichnungbeginn
            } else return true; //im Vertragszeitraum und kein späterer Aufzeichnungbeginn konfiguriert
        } else return false; //außerhalb Vertragszeitraums 
    }
    
    function getVacationEntitlement($year)
    {
        $dezimal_entitlement = $this->contract_hours * $this->monthWithVacationEntitlement($year) * 0.077; 
        $entitlement_hours = floor($dezimal_entitlement);
        $entitlement_minutes = ($dezimal_entitlement - $entitlement_hours) * 60;
        
        if ($entitlement_minutes/60 >= 0.5) {
            $entitlement_hours += 1;
            $entitlement_minutes = 0;
        }
        
        return sprintf("%02s:%02s", $entitlement_hours, round($entitlement_minutes)) ; //round($entitlement_minutes, 3)
    }
    
    
    function getRemainingVacation($year)
    {
        $claimed_vacation = $this->getClaimedVacation($year);
        //Urlaub der mit Resturlaub aus dem Vorjahr verrechnet werden kann wird in diesem Jahr nicht abgezogen
        
        //TODO falls manuell Daten zum Resturlaub des letzten Jahres angegeben wurden werden diese für das entsprechende Jahr genutzt, unhabhängig von sonstigen
        //Vertragsdaten
        if ($this->begin_digital_recording_year == ($year + 1)) {
            $remaining_vacation = $this->last_year_vacation_remaining;
        } else {
        
            
            if ($year-1 >=  date('Y', $this->contract_begin) && $this->getRemainingVacation($year-1) ) {
                $remaining_claimed_vacation = $this->getRemainingVacation($year-1) - $this->getClaimedVacation($year, $month = 3);
                //falls Resturlaub nicht reicht, übrigen Urlaub vom Anspruch in diesem Jahr abziehen
                //positiver Rest verfällt ab April
                if ($remaining_claimed_vacation < 0) {
                    $claimed_vacation += abs($remaining_claimed_vacation);
                }
                //Urlaub bis März diesen Jahres wurde oben bereits verrechnet
                $claimed_vacation = $claimed_vacation - $this->getClaimedVacation($year, $month = 3);
            }

            $remaining_vacation = StundenzettelTimesheet::stundenzettel_strtotimespan($this->getVacationEntitlement($year)) - $claimed_vacation;

            //Urlaub aus den ersten drei Monaten des Folgejahrs kann bei Bedarf verrechnet werden
            if ($remaining_vacation > 0) {
                $remaining_vacation = $remaining_vacation - $this->getClaimedVacation($year+1, $month = 3);
                //falls Resturlaub nicht für die drei ersten Monate des Folgejahres reicht, gilt er als aufgebraucht
                if ($remaining_vacation < 0) {
                    return 0;
                }
            }
        }

        return $remaining_vacation;
    }
    
    function getRemainingVacationAtEndOfYear($year)
    {
        if ($this->begin_digital_recording_year == ($year + 1)) {
            $remaining_vacation_end_of_year = $this->last_year_vacation_remaining;
        } else {
            $remaining_vacation_end_of_year = StundenzettelTimesheet::stundenzettel_strtotimespan($this->getVacationEntitlement($year)) - $this->getClaimedVacation($year);
        }
        return $remaining_vacation_end_of_year;
    }
    
    //default: Urlaub aus allen monatn des Jahres
    //falls $month gesetzt ist: Urlaub bis einschließlich zum Moat $month (für Verrechnung von Resturlaub innrhab des erlaubten Zeitrahmens)
    function getClaimedVacation($year, $month = 12)
    {
        //$timesheets = StundenzettelTimesheet::findBySQL('`contract_id` = ? AND `year` = ?', [$this->id, $year]);
        
        //Urlaub der im Folgejahr bis einschließlich März genommen wurde einbeziehen (möglicherweise ist es übersichtlicher das separat zu bestimmen)
        $timesheets = StundenzettelTimesheet::findBySQL('`contract_id` = ? AND `year` = ? AND `month` <= ?', [$this->id, $year, $month]);
        
        //$timesheets = array_merge($timesheets_thisyear, $timesheets_nextyear);
        
        $claimed_vacation = 0;
        
        foreach ($timesheets as $timesheet) {
            $records = StundenzettelRecord::findBySQL('`timesheet_id` = ? AND `defined_comment` = "Urlaub"', [$timesheet->id]);
            foreach ($records as $record) {
                $claimed_vacation += $record['sum'];
            }
        } 
        //zu Aufzeichnungsbeginn in diesem Jahr bereits genutzter Urlaub
        //falls Aufzeichnungsbeginn vor April diesen Jahres liegt und in diesem Zeitraum bereits Urlaub beansprucht wurde, 
        //kann dieser im Zeitraum Januar-März ggf. mit Resturlaub verrechnet werden
        if ($month <= 3 && $this->begin_vacation_claimed && $this->begin_digital_recording_year == $year && $this->begin_digital_recording_month <= 3) {    
            $claimed_vacation = $claimed_vacation + $this->begin_vacation_claimed;
        //falls Aufzeichnungsbeginn später im Jahr liegt kann Urlaub nur mit Gesamtjahresanspruch verrechnet werden
        } else if ($month > 3 && $this->begin_vacation_claimed && $this->begin_digital_recording_year == $year) {    
            $claimed_vacation = $claimed_vacation + $this->begin_vacation_claimed;
        }
        
        return $claimed_vacation;
    }
    
    function getWorktimeBalance()
    {
        $timesheets = StundenzettelTimesheet::findBySQL('`contract_id` = ?', [$this->id]);
        $balance_time = 0;
        foreach ($timesheets as $timesheet) {
            if ($timesheet->month_completed && $this->monthWithinRecordingTime($timesheet->month, $timesheet->year)) {
                $balance_time += $timesheet->timesheet_balance;
            }
        }
        if ($this->begin_balance) {    
            $balance_time += $this->begin_balance; 
        }
        return $balance_time;
    }

    function reassign_timesheets()
    {
        //durchlaufe alle Monate seit Vertragsbeginn bis heute
        $current_month = date('m', time());
        $current_year = date('Y', time());
        $month = new DateTime();
        $month->setTimestamp($this->contract_begin);
        
        while ($month->getTimestamp() < $this->contract_end){
            //noch kein Stundenzettel zugeordnet 
            if (!StundenzettelTimesheet::getContractTimesheet($this->id, $month->format('m'), $month->format('Y')) ){
                //falls einer existiert, ordne ihn diesem Vertrag zu
                
                //TODO: bestehende Timesheets über Verträge finden
//                if (StundenzettelTimesheet::findBySQL('`user_id` = ? AND `month` = ? AND `year` = ? AND inst_id = ?', [$this->user_id, $month->format('n'), $month->format('Y'), $this->inst_id]) ) {
//                    $timesheet = StundenzettelTimesheet::findOneBySQL('`user_id` = ? AND `month` = ? AND `year` = ? AND inst_id = ?', [$this->user_id, $month->format('n'), $month->format('Y'), $this->inst_id]);
//                    $timesheet->contract_id = $this->id;
//                    $timesheet->store();
                //falls die Vergangenheit betroffen ist, lege nachträglich an
//                } else if ($month->getTimestamp() < time()){
//                    $this->add_timesheet($month->format('n'), $month->format('Y'));
//                }
            }
            $month->modify('+1 month');
        }

    }
    
//    function reassign_timesheets(){
//        $timesheets = StundenzettelTimesheet::findByContract_Id($this->id);
//        foreach ($timesheets as $timesheet){
//            if (!$this->monthPartOfContract($timesheet->month, $timesheet->year)){
//                $matching_contract = $this->getUserContractsByMonth($this->user_id, $timesheet->month, $timesheet->year);
//                if($matching_contract){
//                    $timesheet->contract_id = $matching_contract->id;
//                    $timesheet->store();
//                }
//            }
//        }
//    }
    
    function add_timesheet($month, $year)
    {
        $timesheet = StundenzettelTimesheet::getContractTimesheet($this->id, $month, $year);
        if (!$timesheet) {
            if ( (intval($this->contract_begin) < strtotime($year . '-' . $month . '-28')) && (strtotime($year . '-' . $month . '-01') < intval($this->contract_end)) ) {
                $timesheet = new StundenzettelTimesheet();
                $timesheet->month = $month;
                $timesheet->year = $year;
                $timesheet->contract_id = $this->id;
                $timesheet->store();
            }
        }
    }
}
