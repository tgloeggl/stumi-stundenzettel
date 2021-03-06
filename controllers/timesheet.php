<?php

//require_once __DIR__ . '/../models/...class.php';

class TimesheetController extends StudipController {

    public function __construct($dispatcher)
    {
        parent::__construct($dispatcher);
        $this->plugin = $dispatcher->plugin;

    }

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        PageLayout::setTitle(_("Stundenzettel verwalten"));
        
        // Check permissions to be on this site
        if ( !($this->plugin->hasStumiAdminrole() || $this->plugin->hasStumiContract () || $this->plugin->isStumiSupervisor()) ) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung."));
        }
        
        if ($this->plugin->hasStumiAdminrole ()) {
            $this->adminrole = true;
        }
        if ($this->plugin->hasStumiContract ()) {
            $this->stumirole = true;
        }
        if ($this->plugin->isStumiSupervisor ()) {
            $this->supervisorrole = true;
        }
        
        $this->time_end_pattern =   '^(23:00)|([01]{0,1}[6-9]|[1][0-9]|2[0-2]):[0-5][0-9]$';
        $this->time_begin_pattern = '^([01]{0,1}[6-9]|[1][0-9]|2[0-2]):[0-5][0-9]$';
        $this->break_pattern = '^([0-9]{1,2}):[0-5][0-9]$';
    }

    public function index_action($contract_id = NULL)
    {
        Navigation::activateItem('tools/stundenzettelverwaltung/timesheets');
        
        //allgemeine Stundenzettel-Übersichtsseite für Stumis verwendet automatisch den aktuell laufenden Vertrag
        if (!$contract_id && $this->stumirole) {
            $contract_id = StundenzettelContract::getCurrentContractId($GLOBALS['user']->user_id);
             if (!$contract_id) {
                 $contract_id = StundenzettelContract::getSomeContractId($GLOBALS['user']->user_id);
             }
        }
        $this->contract = StundenzettelContract::find($contract_id);
        $this->timesheets = StundenzettelTimesheet::findByContract_id($contract_id, 'ORDER by `year` ASC, `month` ASC'); 
        $this->stumi = User::find($this->contract->user_id);
        
        $this->records = StundenzettelRecord::findByTimesheet_Id($timesheet_id, 'ORDER BY day ASC');
        
        $this->status_infos = StundenzettelContract::getStaus_array();

    }
    public function admin_index_action()
    {
        if (!($this->adminrole || $this->supervisorrole)){
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung."));
        }
        Navigation::activateItem('tools/stundenzettelverwaltung/timesheets');
        
        //if its later than the 18.th of current month
        if (strftime('%e') > 18) {
            $this->next_month = strftime('%B', strtotime("+1 month"));
            $this->last_month = strftime('%B');
            $this->next_month_num = strftime('%m', strtotime("+1 month"));
            $this->next_month_year = strftime('%Y', strtotime("+1 month"));
            $this->last_month_num = strftime('%m');
            $this->last_month_year = strftime('%Y');
        } else {
            $this->next_month = strftime('%B');
            $this->last_month = strftime('%B', strtotime("-1 month"));
            $this->next_month_num = strftime('%m');
            $this->next_month_year = strftime('%Y');
            $this->last_month_num = strftime('%m', strtotime("-1 month"));
            $this->last_month_year = strftime('%Y', strtotime("-1 month"));
        }
        $this->contracts = StundenzettelContract::getContractsByMonth($this->last_month_num, $this->last_month_year);
        
        foreach ($this->contracts as $contract) {
            $this->timesheets[$contract->id]['last_month'] = StundenzettelTimesheet::getContractTimesheet($contract->id, $this->last_month_num, $this->last_month_year);
            $this->timesheets[$contract->id]['next_month'] = StundenzettelTimesheet::getContractTimesheet($contract->id, $this->next_month_num, $this->next_month_year);
        }
        
        $this->status_infos = StundenzettelContract::getStaus_array();
    }
    
    public function select_action($contract_id, $month = '', $year = '')
    {
        if ( !($this->adminrole || $this->plugin->can_access_contract_timesheets($contract_id))) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        Navigation::activateItem('tools/stundenzettelverwaltung/timesheets');
        if (Request::get('month')) {
            $month = Request::get('month');
            $year = Request::get('year');
        }
        $contract = StundenzettelContract::find($contract_id);
        $this->timesheet = StundenzettelTimesheet::getContractTimesheet($contract_id, $month, $year);
        if (!$this->timesheet && $this->stumirole) {
            if ($contract->monthWithinRecordingTime($month, $year)) {
            //if ( (intval($contract->contract_begin) < strtotime($year . '-' . $month . '-28')) && (strtotime($year . '-' . $month . '-01') < intval($contract->contract_end)) ) {
                $timesheet = new StundenzettelTimesheet();
                $timesheet->month = $month;
                $timesheet->year = $year;
                $timesheet->contract_id = $contract_id;
                $timesheet->store();
                $this->redirect('timesheet/timesheet/' . $timesheet->id);
            } else {
                PageLayout::postMessage(MessageBox::error(_("Dieser Monat liegt außerhalb des Vertragszeitraums (" . date('d.m.Y',$contract->contract_begin) . "-" . date('d.m.Y',$contract->contract_end) . ") oder außerhalb des digital zu erfassenden Zeitraums."))); 
                $this->no_timesheet = true;
                $this->month = $month;
                $this->year = $year;
                $this->contract = $contract;
                $this->render_action('timesheet');
            }
        } else if (!$this->timesheet && $this->adminrole){
            PageLayout::postMessage(MessageBox::error(_("Für diesen Monat liegt kein Stundenzettel vor."))); 
            $this->render_action('timesheet');
        } else {
            $this->redirect('timesheet/timesheet/' . $this->timesheet->id);
        }
        
    }
    
    
    public function timesheet_action($timesheet_id = NULL)
    {
         if ( ($timesheet_id) && !($this->adminrole || $this->plugin->can_access_timesheet($timesheet_id))) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        if ($this->stumirole){
            Navigation::activateItem('tools/stundenzettelverwaltung/timetracking');
        } else {
            Navigation::activateItem('tools/stundenzettelverwaltung/timesheets');
        }
        
        $sidebar = Sidebar::Get();
        //Sidebar::Get()->setTitle('Stundenzettel von ' . $GLOBALS['user']->username);
        
        if(!$timesheet_id && $this->stumirole){
            $contract_id = StundenzettelContract::getCurrentContractId($GLOBALS['user']->user_id);
            //$timesheet = StundenzettelTimesheet::getContractTimesheet($contract_id, date('m'), date('Y'));
            //$timesheet_id = $timesheet->id;
            if (!$contract_id){
                $contract_id = StundenzettelContract::getSomeContractId($GLOBALS['user']->user_id);
            }
            $this->redirect('timesheet/select/' . $contract_id . '/' . date('m') . '/' . date('Y'));
        }

        $this->timesheet = StundenzettelTimesheet::find($timesheet_id);  
        $this->days_per_month = cal_days_in_month(CAL_GREGORIAN, $this->timesheet->month, $this->timesheet->year); 
        $this->inst_id = $this->timesheet->contract->inst_id;
        $this->user_id = $this->timesheet->contract->user_id;
        $this->records = StundenzettelRecord::findByTimesheet_Id($timesheet_id, 'ORDER BY day ASC');
        
        if($this->timesheet->locked || $this->adminrole) {
            if($this->adminrole || $this->supervisorrole) {
                if ($this->timesheet->finished){
                    PageLayout::postMessage(MessageBox::info(_("Bearbeitung gesperrt. Sie sind nicht berechtigt Änderungen vorzunehmen"))); 
                } else {
                    PageLayout::postMessage(MessageBox::info(_("Digitaler Stundenzettel wurde noch nicht eingereicht.")));
                }
                
            } else {
                PageLayout::postMessage(MessageBox::info(_("Der Stundenzettel wurde bereits eingereicht und kann nicht mehr bearbeitet werden. Sollten Änderungen nötig sein, kontaktiere deine/n zuständigen Ansprechpartner/in.")));
            }
        }
        
        if($this->stumirole){
            $actions = new ActionsWidget();
            $actions->setTitle('Aktionen');
            
            if (!$this->timesheet->finished) {
                $actions->addLink(
                        _('Stundenzettel einreichen'),
                        PluginEngine::getUrl($this->plugin, [], 'timesheet/send/' . $timesheet_id ),
                        Icon::create('share', 'new'),
                        ['title' => 'Achtung, anschließend keine Bearbeitung mehr möglich!',
                            'data-confirm' => 'Bearbeitung abschließen und Stundenzettel offiziell einreichen?']
                    );
            } else {
                $actions->addLink(
                        _('Stundenzettel wurde eingereicht'),
                        PluginEngine::getUrl($this->plugin, [], 'timesheet/timesheet/' . $timesheet_id ),
                        Icon::create('lock-locked', 'new'),
                        ['title' => 'Keine Bearbeitung mehr möglich!',
                            'disabled' => "disabled"]
                    );
            }
            if ($this->timesheet->finished) {
                $actions->addLink(
                    _('PDF-zum Ausdruck generieren'),
                    PluginEngine::getUrl($this->plugin, [], 'timesheet/pdf/' . $timesheet_id ),
                    Icon::create('file-pdf')
                    //,['onclick'=>"return validateFormSaved()"]
                );
            }
            $sidebar->addWidget($actions);
        }

    }
    
    
    public function save_timesheet_action($timesheet_id)
    {
        
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        if ( !($timesheet->contract->user_id == User::findCurrent()->user_id)) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
         if (!$timesheet->locked) {
             
            $record_ids_array = Request::getArray('record_id');
            $begin_array = Request::getArray('begin');
            $end_array = Request::getArray('end');
            $break_array = Request::getArray('break');
            $sum_array = Request::getArray('sum');
            $mktime_array = Request::getArray('entry_mktime');
            $defined_comment_array = Request::getArray('defined_comment');
            $comment_array = Request::getArray('comment');

            $limit = count($begin_array);
            for ($i = 1; $i <= $limit; $i++) {

                $errors = false;
                $record = StundenzettelRecord::find([$timesheet_id, $i]);
                if (!$record) {
                    $record = new StundenzettelRecord();
                    $record->timesheet_id = $timesheet_id;
                    $record->day = $i;
                }
                    if ($begin_array[$i]) {
                        $record->begin = strtotime($record->getDate() . ' ' . $begin_array[$i]);
                        if ($record->begin < strtotime($record->getDate() . ' 06:00') ){
                            PageLayout::postMessage(MessageBox::error(sprintf(_("Arbeitszeit kann frühestens ab 6 Uhr erfasst werden: %s.%s"), $record->day, $timesheet->month ))); 
                            $errors = true;
                        }
                    } else if ($record->begin){
                        $record->begin = NULL;
                    }
                    if ($end_array[$i]) {
                        $record->end = strtotime($record->getDate() . ' ' . $end_array[$i]);
                        if ($record->end > strtotime($record->getDate() . ' 23:00') ){
                            PageLayout::postMessage(MessageBox::error(sprintf(_("Arbeitszeit kann bis maximal 23 Uhr erfasst werden: %s.%s"), $record->day, $timesheet->month ))); 
                            $errors = true;
                        }
                    } else if ($record->end){
                        $record->end = NULL;
                    }
                    if ($break_array[$i]) {
                        $record->break = StundenzettelTimesheet::stundenzettel_strtotimespan($break_array[$i]);
                    } else if ($record->break){
                        $record->break = NULL;
                    }
                    $record->entry_mktime = $mktime_array[$i];
                    $record->defined_comment = ($record->isHoliday()) ? 'Feiertag' : $defined_comment_array[$i];
                    $record->comment = ($record->isUniClosed()) ? '' : $comment_array[$i];
                    if ($record->defined_comment == 'Urlaub'){
                        $record->sum =  StundenzettelTimesheet::stundenzettel_strtotimespan($sum_array[$i]);
                    } else {
                        $record->calculate_sum();
                    }
                    if ($record->sum < 0) {
                        PageLayout::postMessage(MessageBox::error(sprintf(_("Gesamtsumme der Arbeitszeit pro Tag muss positiv sein: %s.%s"), $record->day, $timesheet->month ))); 
                        $errors = true;  
                    } else if ($record->sum > (10*3600)) {
                        PageLayout::postMessage(MessageBox::error(sprintf(_("Die tägliche Arbeitszeit darf 10 Stunden nicht überschreiten: %s.%s"), $record->day, $timesheet->month ))); 
                        $errors = true;  
                    } else if ($record->sum && ($record->sum > (9*3600)) && ($record->break < 2700)){
                        PageLayout::postMessage(MessageBox::error(sprintf(_("Bei einer Arbeitszeit von mehr als neun Stunden ist eine Pause von mindestens 45 Minuten gesetzlich vorgeschrieben: %s.%s"), $record->day, $timesheet->month )));
                        $errors = true;  
                    } else if ($record->sum && ($record->sum > (6*3600)) && ($record->break < 1800)){
                        PageLayout::postMessage(MessageBox::error(sprintf(_("Bei einer Arbeitszeit von mehr als sechs Stunden ist eine Pause von mindestens 30 Minuten gesetzlich vorgeschrieben: %s.%s"), $record->day, $timesheet->month )));
                        $errors = true;  
                    } 
                    if ($record->sum && !$record->entry_mktime) {
                        PageLayout::postMessage(MessageBox::error(sprintf(_("fehlende Angabe -Aufgezeichnet am- für den %s.%s"), $record->day, $timesheet->month )));
                        $errors = true; 
                    }
                    
                    if (!$errors){
                        $record->store();
                    }
                    
            }

            $timesheet = $record->timesheet;
            $timesheet->calculate_sum();

            PageLayout::postMessage(MessageBox::success(_("Änderungen gespeichert."))); 
            $this->redirect('timesheet/timesheet/' . $timesheet_id);
            
         } else {
            PageLayout::postMessage(MessageBox::success(_("Speichern nicht möglich. Bearbeitung ist gesperrt."))); 
            $this->redirect('timesheet/timesheet/' . $timesheet_id);
         }
    }
    
    public function pdf_action($timesheet_id)
    {
        
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        if ( !($timesheet->contract->user_id == User::findCurrent()->user_id)) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        if($timesheet){
            $timesheet->build_pdf();
            $this->render_nothing();
        } else {
            PageLayout::postMessage(MessageBox::success(_("Stundenzettel konnte nicht generiert werden.")));
            $this->redirect('timesheet/index');
        }
    }
    
    public function send_action($timesheet_id)
    {
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        if ( !($timesheet->contract->user_id == User::findCurrent()->user_id)) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        if($timesheet){
            $timesheet->finished = true;
            $timesheet->store();
            PageLayout::postMessage(MessageBox::success(_("Bitte denke daran den Stundenzettel zusätzlich ausgedruckt und unterschrieben Deinem/Deiner Vorgesetzten vorzulegen.")));
            $this->redirect('timesheet/timesheet/' . $timesheet->id);
        } else {
            PageLayout::postMessage(MessageBox::error(_("Fehler: kein Stundenzettel gefunden.")));
            $this->redirect('timesheet/index');
        }
    }
    
    public function approve_action($timesheet_id)
    {
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        
        if ( !($timesheet->contract->supervisor == User::findCurrent()->user_id)) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        if($timesheet && User::findCurrent()->user_id == $timesheet->contract->supervisor){
            $timesheet->approved = true;
            $timesheet->store();
            PageLayout::postMessage(MessageBox::success(_("Korrektheit der Angaben wurde von Ihnen bestätigt.")));
            $this->redirect('timesheet/index/'. $timesheet->contract->id);
        } else {
            PageLayout::postMessage(MessageBox::error(_("Fehler: Sie sind nicht berechtigt diesen Stundenzettel zu bewerten.")));
            $this->redirect('timesheet/index');
        }
    }
    
    public function received_action($timesheet_id)
    {
        if ( !$this->adminrole ) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        if($timesheet && $this->adminrole){
            $timesheet->received = true;
            $timesheet->store();
            PageLayout::postMessage(MessageBox::success(_("Vorliegen in Papierform bestätigt: ") . htmlready($timesheet->contract->stumi->nachname) . '/' . strftime('%B', strtotime("2020-" . $timesheet->month . "-01") )) );
            $this->redirect('timesheet/admin_index/'. $timesheet->contract->id);
        } else {
            PageLayout::postMessage(MessageBox::error(_("Fehler: Sie sind zu dieser aktion nicht berechtigt.")));
            $this->redirect('timesheet/index');
        }
    }
    
    public function complete_action($timesheet_id)
    {
        if ( !$this->adminrole ) {
            throw new AccessDeniedException(_("Sie haben keine Zugriffsberechtigung"));
        }
        
        $timesheet = StundenzettelTimesheet::find($timesheet_id);
        if($timesheet && $this->adminrole){
            $timesheet->complete = true;
            $timesheet->store();
            PageLayout::postMessage(MessageBox::success(_("Vorgang abgeschlossen.")));
            $this->redirect('timesheet/admin_index/'. $timesheet->contract->id);
        } else {
            PageLayout::postMessage(MessageBox::error(_("Fehler: Sie sind zu dieser aktion nicht berechtigt.")));
            $this->redirect('timesheet/index');
        }
    }
    
}
