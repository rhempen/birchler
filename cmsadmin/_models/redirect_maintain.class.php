<?php
/**
 * ----------------------------------------------------------
 * (c) 2007  Roland Hempen
 *           www.hempenweb.ch
 * ----------------------------------------------------------
 *
 * Klasse f�r die Verwaltung der Index-Tabelle cms_redirect
 * @author      Roland Hempen
 * @copyright   Frei einsetz- und veraenderbar, wenn der Autor erw�hnt wird
 * @version     1.0 | 2008-12-28
 */
 
class redirectMaintain
{
	private $mPrefix;
	private $mDb;
	private $mMsg;

    /* MyRequire-Klasse mit Zugangsdaten aufrufen */
    function __construct($db) 
    {
		$this->mPrefix = TABLE_PREFIX;
		$this->mDb = $db;
		$this->mMsg = array();
    }                                                                          

   /**************************************************************************
	* public functions
	***************************************************************************/
	
    /* Datensatz f�r eine Unterseite in cms_redirect einf�gen
	   @params: $_POST-Array 
	   @return: $msg -> Meldung
	*/
    public function page_save_redirect() 
    { 
		global $db, $msg, $genmain;
		$navid = 0;
		$subid = 0;
		$ids   = array();
		$pagid = (int)$_POST['page_id'];
		$kuerzel = $_POST['name'] != '' ? $_POST['name'] : $_POST['seite'];
		$kuerzel = $genmain->format_kuerzel($kuerzel);
		
		// navid und subid aus cms_navigation ermitteln 
		if ((int)$_POST['navid'] !=0) {
			$ids = $this->get_navids($_POST['navid']);
			$subid = isset($ids['subid']) ? $ids['subid'] : 0;
			$navid = isset($ids['navid']) ? $ids['navid'] : 0;
			// Neuer Datensatz ins cms_redirect einf�gen
			$msg   = $this->insert_redirect($navid, $subid, $pagid, $kuerzel);
		} else {
			$msg[] = 'error'; $msg[] = $GLOBALS['MESSAGES']['REDIRECT_NO_NAVID'];
		}
		return $msg;
    }


    /* Datensatz f�r eine Unterseite in cms_redirect updaten
    	Wenn die Zuordnung einer Unterseite zur navigation ge�ndert hat, muss 
    	1. der bisherige Satz gel�scht werden
    	2. eine neuer Satz mit den neuen ID's eingef�gt werden
    	Wenn der Name der Seite ge�ndert wurde muss auch das k�rzel aktualisiert werden
	   @params: $_POST-Array, $akt_redir  
	   @return: $msg -> Meldung
	*/
    public function page_update_redirect() 
    { 
      global $akt_redir, $db, $msg, $genmain;
      $nav_id_neu = isset($_POST['navid']) ? $_POST['navid'] : 0;

      $kuerzel = $_POST['name'] != '' ? $_POST['name'] : $_POST['seite'];
      $kuerzel = $genmain->format_kuerzel($kuerzel);

      // wurde die Zuordnung ge�ndert? 
      $ids 	= $this->get_navids($nav_id_neu);
      $subid 	= isset($ids['subid']) ? $ids['subid'] : 0;
      $navid 	= isset($ids['navid']) ? $ids['navid'] : 0;

      // Wenn alte und neue ID's nicht identisch sind -> l�schen alt, insert neu
      if ($navid != $akt_redir['navid'] || $subid != $akt_redir['subid']) {
          $msg = $this->delete_redirect($akt_redir['navid'], $akt_redir['subid'], $akt_redir['pagid']);
          $msg = $this->insert_redirect($navid, $subid, $akt_redir['pagid'], $kuerzel);			
      } 
      // Ev. muss nur das K�rzel ge�ndert werden
      elseif ($kuerzel != $akt_redir['kuerzel']) {
          $msg = $this->update_redirect($navid, $subid, $akt_redir['pagid'], $kuerzel);
      }		
      return $msg;
    }
    
    	
    /* Datensatz f�r eine Unterseite in cms_redirect l�schen
	   @params: $_POST-Array 
	   @return: $msg -> Meldung
	*/
    public function page_delete_redirect($row) 
    { 
      global $db, $msg;
      $navid = $row['nav_id'];
      $pagid = $row['page_id'];
      $subid = 0;
      $ids   = array();

      if ($navid != 0) {
          $ids = $this->get_navids($navid);
          $subid = isset($ids['subid']) ? $ids['subid'] : 0;
          $navid = isset($ids['navid']) ? $ids['navid'] : 0;
          // Eintrag l�schen
          $msg = $this->delete_redirect($navid, $subid, $pagid);			
      } else {
          $msg[] = 'error'; $msg[] = $GLOBALS['MESSAGES']['REDIRECT_NO_NAVID'];			
      }
      return $msg;
    }
    

    /* Datensatz f�r eine Unterseite in cms_redirect ermitteln
	   @params: $pagid, $navid -> kann sowohl ID einer Haupt-, als auch einer Unternavigations sein
	   @return: $row = Datensatz
	*/
    public function page_read_redirect($pagid, $navid) 
    { 
      global $db;
      // erst nach Hauptnavigation suchen
      $query = 'SELECT * FROM '.$this->mPrefix.'redirect WHERE navid='.$navid.' AND pagid='.$pagid;
      $row = $db->queryRow($query);
      if ($db->queryOne('SELECT FOUND_ROWS()') == 0) {
          // Wenn kein Datensatz gefunden, Eintrag zu einer Unternavigation suchen
          $query = 'SELECT * FROM '.$this->mPrefix.'redirect WHERE subid='.$navid.' AND pagid='.$pagid;
          $row = $db->queryRow($query);			
      } 
      return $row;
    }
    	
    /* Kuerzel aus Update des Kuerzels in cms_navigation / cms_pages updaten
       	@params: $navid -> dies kann die navid eines Haupt- oder Unternavigationspunktes sein
    	@return: Kein Returnwert, da der Update mittels AJAX erfolgt.
    */
    public function navi_update($navid, $kuerzel, $pageid=0) 
    {
      global $naviga, $db, $general, $genmain;
      $subid = 0;
      $ids   = array();

      $kuerzel = $genmain->format_kuerzel($kuerzel);
//		$kuerzel = strtolower(str_replace(' ','_',$kuerzel));

      if ($navid != 0 && $navid != null) {
          $ids = $this->get_navids($navid);
          $subid = isset($ids['subid']) ? $ids['subid'] : 0;
          $navid = isset($ids['navid']) ? $ids['navid'] : 0;
          $update = 'UPDATE '.$this->mPrefix.'redirect SET kuerzel="'. $kuerzel .'"';
          $update .= ' WHERE navid='.$navid.' AND subid='.$subid.' AND pagid='.$pageid;
          $db->query($update);
      }
    }
    
    
    /* Index-Tabelle cms_redirect refreshen bzw. initialisieren 
    	@return: Kein Returnwert, aber Ausgabe der eingef�gten Datens�tze
    	1. Tabelle cms_redirect l�schen
    */
    public function smurl_update() 
    {
    	global $msg;
		$this->smurl_truncate();
		$msg = $this->smurl_navi_insert();
		$msg = $this->smurl_page_insert();
		return $msg;
    }    
    
    /* Index-Tabelle cms_redirect komplett l�schen */
    private function smurl_truncate() 
    {
    	global $db;
    	$delete = 'TRUNCATE TABLE '.$this->mPrefix.'redirect';
    	$db->query($delete);
    }    
    
    /* Index-Tabelle cms_redirect aus cms_navigation updaten */
    private function smurl_navi_insert() 
    {
    	global $db, $naviga, $msg, $genmain;
		$msg[] = 'message'; $msg[] = $GLOBALS['CMS']['MENU01'];
    	$navigation = $naviga->navigation_laden();
        while ($row = $navigation->fetchRow(MDB2_FETCHMODE_ASSOC)) 
        {
            $pagid = 0;
            $subid = 0;
//                $bezeichnung = utf8_encode($row['bezeichnung']);
            $bezeichnung = $row['bezeichnung'];
            $kuerzel = $genmain->format_kuerzel($bezeichnung);

            if ($row['ukap'] == 0) { 
                $kap_save = $row['kap']; 
                $navid_save = $row['nav_id'];
                $navid = $row['nav_id'];
            }

            if ($row['ukap'] != 0 && $row['kap'] == $kap_save) {
                $navid = $navid_save;
                $subid = $row['nav_id'];
            } 

            // Neuer Datensatz ins cms_redirect einf�gen
            $msg = $this->insert_redirect($navid, $subid, $pagid, $kuerzel);
            }
        return $msg;
    }    

    /* Index-Tabelle cms_redirect aus cms_navigation updaten */
    private function smurl_page_insert() 
    {
    	global $db, $pages, $msg, $genmain;
        $msg[] = 'message'; $msg[] = $GLOBALS['CMS']['MENU02'];
    	$allpages = $pages->read_all_pages();
        while ($row = $allpages->fetchRow(MDB2_FETCHMODE_ASSOC)) 
        {
            unset($ids);
            $subid = 0;
            $pagid = $row['page_id'];
            $name = $row['name'];
            $kuerzel = $genmain->format_kuerzel($name);
            // Zuordnung aus cms_navigation ermitteln? 
            $ids 	= $this->get_navids($row['nav_id']);
            $subid 	= isset($ids['subid']) ? $ids['subid'] : 0;
            $navid 	= isset($ids['navid']) ? $ids['navid'] : 0;			
            // Neuer Datensatz ins cms_redirect einf�gen
            $msg = $this->insert_redirect($navid, $subid, $pagid, $kuerzel);
        }
        return $msg;    	
    }
    
    /* Datensatz in cms_redirect einf�gen
	   @params: $navid, $subid, $pagid 
	   @return: $msg -> Meldung
	*/
    private function insert_redirect($navid, $subid, $pagid, $kuerzel) 
    {
    	global $db, $msg;
        $par = '-> $navid-$subid-$pagid-$kuerzel='.$navid.'-'.$subid.'-'.$pagid.'-'.$kuerzel;    	
        if ($navid !=0) {	
            // Eintrag einf�gen
            $insert = 'INSERT INTO '.$this->mPrefix.'redirect (navid,subid,pagid,kuerzel)'.
            $insert .= ' VALUES ('.$navid.','.$subid.','.$pagid.',"'.$kuerzel.'")';

            if ($db->query($insert)) {
                $msg[] = 'success'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_GESPEICHERT'], $par);		
            } else {
                $msg[] = 'error'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_NICHT_GESPEICHERT'], $par);
            }
        } else {
            $msg[] = 'error'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_NO_NAVID'], $par);
        }
    	return $msg;
    }
    

    /* Datensatz in cms_redirect aktualisieren
	   @params: $navid, $subid, $pagid, $kuerzel 
	   @return: $msg -> Meldung
	*/
    private function update_redirect($navid, $subid, $pagid, $kuerzel) 
    {
    	global $db, $msg;
        $par = '-> key='.$navid.'-'.$subid.'-'.$pagid.'-'.$kuerzel;    	
        if ($navid !=0 && $pagid !=0) {	
            // Eintrag einf�gen
            $update = 'UPDATE '.$this->mPrefix.'redirect SET kuerzel="'.$kuerzel.'"'.
            $update .= ' WHERE navid='.$navid.' AND subid='.$subid.' AND pagid='.$pagid;

            if ($db->query($update)) {
                $msg[] = 'success'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_GESPEICHERT'], $par);		
            } else {
                $msg[] = 'error'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_NICHT_GESPEICHERT'], $par);
            }
        } else {
            $msg[] = 'error'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_NO_NAVID'], $par);
        }
    	return $msg;
    }

    
    /* Datensatz in cms_redirect l�schen
	   @params: $navid, $subid, $pagid
	   @return: $msg -> Meldung
	*/
    private function delete_redirect($navid, $subid, $pagid) 
    {
    	global $db, $msg;
		$par = '-> key='.$navid.'-'.$subid.'-'.$pagid;    	
		if ($navid !=0 && $pagid !=0) {	
			$delete = 'DELETE FROM '.$this->mPrefix.'redirect'; 
			$delete .= ' WHERE navid='.$navid.' AND subid='.$subid.' AND pagid='.$pagid;
		
			$par = '-> key='.$navid.'-'.$subid.'-'.$pagid;
			if ($db->query($delete)) {
				$rows_affected = $db->queryOne('SELECT ROW_COUNT()');	
				if ($rows_affected != 0) {
					$msg[] = 'success'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_GELOESCHT'], $par);		
				} else {
					$msg[] = 'error'; $msg[] =  sprintf($GLOBALS['MESSAGES']['REDIRECT_NICHT_GELOESCHT'], $par);					
				}
			} else {
				$msg[] = 'error'; $msg[] =  sprintf($GLOBALS['MESSAGES']['REDIRECT_NICHT_GELOESCHT'], $par);
			}
		} else {
			$msg[] = 'error'; $msg[] = sprintf($GLOBALS['MESSAGES']['REDIRECT_NO_NAVID'], $par);
		}
    	return $msg;
    }

    
	/* Id's aus cms_navigation ermitteln
    	@params: $navid -> dies kann die navid eines Haupt- oder Unternavigationspunktes sein
    	@return: $ids -> array mit navid und subid
    */
    private function get_navids($navid) 
    {
    	global $naviga, $ids;
	    unset($ids);
        $row = $naviga->read_navigation_by_id($navid);
        if ($row['kap'] != 0 && $row['ukap'] != 0) {
            $ids['subid'] = $row['nav_id'];
            $ids['navid'] = $naviga->read_navid_by_kap($row['kap']);
        } else {
            $ids['navid'] = $row['nav_id'];
        }
        return $ids;
	  }
} 

?>
