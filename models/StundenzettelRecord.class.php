<?php

/**
 * @author  <asudau@uos.de>
 *
 * @property varchar       $id
 * @property varchar       $timesheet_id
 * @property int           $day
 * @property int           $begin
 * @property int           $end
 * @property int           $break
 * @property decimal       $sum
 * @property enum          $defined_comment 
 * @property varchar       $comment
 * @property int           $entry_mktime

 */

class StundenzettelRecord extends \SimpleORMap
{
    private static $holidays_nds = array(
        '01.01.' => 'Neujahr',
        '10.04.' => 'Karfreitag',
        '01.05.' => 'Tag der Arbeit',
        '21.05.' => 'Himmelfahrt',
        '01.06.' => 'Pfingsten',
        '03.10.' => 'Tag der Deutschen Einheit',
        '31.10.' => 'Reformationstag',
        '24.12.' => 'Heiligabend',
        '25.12.' => '1. Weihnachstfeiertag',
        '26.12.' => '2. Weihnachtsfeiertag',
        '31.12.' => 'Silvester'
        );
    
    private static $uni_closed = array(
        '27.12.' => 'Universitätsbetrieb geschlossen',
        '28.12.' => 'Universitätsbetrieb geschlossen',
        '29.12.' => 'Universitätsbetrieb geschlossen',
        '30.12.' => 'Universitätsbetrieb geschlossen'
        );
    
    
    protected static function configure($config = array())
    {
        $config['db_table'] = 'stundenzettel_records';
        
        $config['belongs_to']['timesheet'] = [
            'class_name'  => 'StundenzettelTimesheet',
            'foreign_key' => 'timesheet_id',];
        
        parent::configure($config);
    }
    
    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->registerCallback('before_store', 'before_store');
    }
    
    protected function before_store()
    {
        if ($this->sum < 0){
            throw new Exception(sprintf(_('Gesamtsumme der Arbeitszeit pro Tag muss positiv sein!')));
        }
    }
    
    function calculate_sum(){
        if(in_array($this->defined_comment, ['Urlaub', 'Krank', 'Feiertag']) && !$this->isWeekend() ) {
            $this->sum = StundenzettelTimesheet::stundenzettel_strtotimespan($this->timesheet->contract->default_workday_time);
        } else {//if( $this->begin > 0 && $this->end > $this->begin ) {
            $this->sum = $this->end - $this->begin - $this->break;
        } //else {
//            $this->sum = '';
//        }
    }
    
    function getWeekday() 
    {
        return date('w', strtotime($this->getDate()));
    }
    
    function getDate() 
    {
        $timesheet = StundenzettelTimesheet::find($this->timesheet_id);
        return sprintf("%02s", $this->day) . '.' . sprintf("%02s", $timesheet->month) . '.' . sprintf("%02s", $timesheet->year);
    }
    
    function isWeekend()
    {
        return in_array($this->getWeekday(), ['6', '0']);
    }
    
    function isHoliday()
    {
        return array_key_exists(substr($this->getDate(),0,6), self::$holidays_nds);
    }
    
    function isUniClosed()
    {
        return array_key_exists(substr($this->getDate(),0,6), self::$uni_closed);
    }
    
    static function isDateWeekend($date)
    {
        $day = date('w', strtotime($date));
        return in_array($day, ['6', '0']);
    }
    
    static function isDateHoliday($date)
    {
        $day = date('d.m.', strtotime($date));
        return array_key_exists($day, self::$holidays_nds);
    }
    
    static function isUniClosedOnDate($date)
    {
        return array_key_exists(substr($date, 0, 6), self::$uni_closed);
    }
    
    static function isEditable($date)
    {
        $date_time = new DateTime($date);
        $today = new DateTime('now');
        return (!self::isUniClosedOnDate($date) && !self::isDateHoliday($date) && !self::isDateWeekend($date) && ($date_time <= $today));
    }
}