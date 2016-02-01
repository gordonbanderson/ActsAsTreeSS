<?php

class ActsAsTreeExtension extends DataExtension {
	private static $db = array(
		'Depth' => 'Int',
		'Lineage' => 'Varchar(255)',
		'OldLineage' => 'Varchar(255)',
		'SubTreeRequiresUpdate' => 'Boolean',
		'LineageState' => 'Enum("Updated,Updating,UpdatingAsPartOfSubTree")'
	);

	private static $indexes = array(
		'Depth' => true,
		'Lineage' => true
	);

    private static $defaults = array('LineageState' => 'Updating');

    private $completedWrite = false;

	//private $oawctr = 0;
/*
------- on before write cow = --------
OBW 10:A different title
LS:Updating
OBW: CHANGED:1
------- on after write --------
OAW 10, LS=Updating
OAW: LINEAGE SET TO UPDATED
OAW: WRITING 10
------- on before write cow = 1--------
OBW 10:A different title
LS:Updated
OBW: CHANGED:1
------- on after write --------
OAW 10, LS=Updated
A different title ID=10, D=3, L=0000900010
F


 */
	public function onBeforeWriteNOT() {
        error_log('------- on before write cow = ' . $this->completedWrite . '--------');
        error_log(print_r($this,1));
        error_log('OBW ' .$this->owner->ID . ':' . $this->owner->Title);
        error_log('LS:' . $this->owner->LineageState);
        //error_log(print_r($this->owner->record,1));
        error_log('OBW: CHANGED:' . $this->owner->isChanged());
        error_log('RECORD:');
        error_log($this->owner->record['ParentIDdfd']);

       // if ($this->owner->isChanged('ParentID') && !$this->completedWrite) {
        if (!$this->completedWrite) {
            error_log('OBW: **** MOVE ****');
			$this->owner->SubTreeRequiresUpdate = true;
			$this->owner->OldLineage = $this->owner->Lineage;
			$this->owner->LineageState = 'Updating';
            error_log('OBW: LINEAGE SET TO UPDATING');
		}

                    error_log('OBW: Depth = ' . $this->owner->Depth);


		parent::onBeforeWrite();
	}


	public function onAfterWriteNOT() {
        error_log('------- on after write --------');
        error_log('OAW ' .$this->owner->ID . ', LS=' . $this->owner->LineageState);
		//$this->oawctr++;

		if (!$this->completedWrite && $this->owner->LineageState != 'Updated') {
            error_log('CHECKING PARENT ID ' . $this->owner->ParentID);
			if ($this->owner->ParentID == 0) {
                error_log('OAW: DEPTH T1');
				$this->owner->Depth = 1;

				$this->owner->Lineage = $this->paddedNumber($this->owner->ID);
			} else {
                error_log('OAW: DEPTH T2');
				$pc = $this->owner->Parent();
                error_log('OAW: PARENT LINEAGE: ' . $pc->Lineage);
                error_log('OAW: PARENT DEPTH: ' . $pc->Depth);
				$this->owner->Depth = $pc->Depth + 1;
				$this->owner->Lineage = ($pc->Lineage).$this->paddedNumber($this->owner->ID);
                error_log('**** OAW: SET TO, THIS LINEAGE: ' . $this->owner->Lineage);
                asdfsdf;
			}
            $this->owner->LineageState = 'Updated';
            $this->completedWrite = true;
            error_log('OAW: LINEAGE STATE SET TO UPDATED');
            error_log('OAW: LINEAGE SET TO ' . $this->owner->Lineage);
            error_log('OAW: WRITING ' . $this->owner->ID);
            error_log('OAW: Depth = ' . $this->owner->Depth);
			$result = $this->owner->write();
		} else {
            error_log('>>>> NOT WRITING DEPTH <<<<');
            error_log('OAW: LINEAGE IS ' . $this->owner->Lineage);
            error_log('OAW: LS ' . $this->owner->LineageState);
            error_log('OAW: ID ' . $this->owner->ID);
            error_log('OAW: Depth = ' . $this->owner->Depth);
            error_log('>>>> /NOT WRITING DEPTH <<<<');
        }

		parent::onAfterWrite();

        if (!empty($this->owner->OldLineage)) {
            error_log('%%%%% OLD LINEAGE FOUND, SUBTREE NEEDS TWEAKED ');
            $this->updateSubtree($this->owner->OldLineage);
            //error_log(print_r($this,1));
        }

		/*
		subtree require fixing?
		if so
			- find the old lineage
			- find the old depth
			- find all the old items for the current stage starting with the old lineage
				- i) add delta to the depth
				- ii) Update lineage by search and replace
			- empty old lineage, old depth fields
		 */
	}


    private $oawCtr = 0;

    /*
    Calculate Lineage after write, as ID is required first
     */
    public function onAfterWrite() {
        $this->oawCtr++;
        error_log("\n\n\n\n" . 'ON AFTER CTR: ' . $this->oawCtr);
        error_log('ON AFTER WRITE: ' . $this->owner->ID);
        error_log('ON AFTER WRITE PID=: ' . $this->owner->ParentID);
        error_log('ON AFTER WRITE LineageState=: ' . $this->owner->LineageState);

        error_log('PARENT CHANGED? ' . $this->owner->isChanged('ParentID'));


        if ($this->owner->LineageState != 'Updated' || $this->oawCtr == 3) {
            if ($this->owner->ParentID == 0) {
                $this->owner->Depth = 1;
                $this->owner->Lineage = $this->paddedNumber($this->owner->ID);
            } else {
                $parent = $this->owner->Parent();
                $this->owner->Depth = $parent->Depth + 1;
                error_log('Stetching lineage');
                $this->owner->Lineage = $parent->Lineage . $this->paddedNumber($this->owner->ID);
            }
            $this->owner->LineageState = 'Updated';
            $this->owner->write();
        }

    }

    /*
    Search for pages matching a given lineage and update them to the current one
    @param $lineageToUpdate The lineage to update, usually the old lineage after
            a page has been moved
     */
    private function updateSubtree($lineageToUpdate) {
        // FIXME, sql injection
        $pages = SiteTree::get()->where('Lineage LIKE \'' . $lineageToUpdate .'%\'')->sort('Depth');
        foreach ($pages as $page) {
            error_log('UPDATE REQUIRED: ' .$page->Lineage);
        }
    }


	private function paddedNumber($i) {
		// fixme, use config
		$result = str_pad($i, 5, '0', STR_PAD_LEFT);
		return $result;
	}

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		// FIXME - take account of locales
		DB::query('UPDATE SiteTree set Depth=1 where ParentID = 0;');

		// add depth to comments missing this value
		$maxthreaddepth = 5; //SiteTree::get_config_value('ActsAsTree', 'maximum_thread_comment_depth');

		$clazzname = $this->owner->ClassName;
		$suffixes = array('','_Live');


		for ($i=0; $i <= $maxthreaddepth; $i++) {
			foreach ($suffixes as $suffix) {
				$sql = "SELECT ID FROM "
                     . "SiteTree{$suffix} WHERE Depth=" . $i;
                $records = DB::query($sql);
                $ids = array();
                foreach ($records as $record) {
                    array_push($ids, $record['ID']);
                }

                if (sizeof($ids) > 0) {
                    $csvIDs = Convert::raw2sql(implode(',', $ids));
                    $sql = 'UPDATE SiteTree'.$suffix
                    . " SET Depth=" . ($i+1) . ", LineageState='Updating' WHERE ParentID IN ($csvIDs)"
                    . " AND DEPTH != " . ($i+1);
                    DB::query($sql);
                }
			}

		}

		$ctr = 0;

		for ($i=0; $i <= $maxthreaddepth; $i++) {
            $pages =  SiteTree::get()->filter('Depth',$i);
			$pages =  SiteTree::get()->filter('Depth',$i)->where("Lineage IS NULL");

			foreach ($pages as $page) {
				// write the page, this will update the lineage
				$page->write();

				// check for a live version and publish the draft version if a live page exists
				//$livepage = Versioned::get_by_stage('SiteTree', 'Live')->byID($page->ID);
				//if ($livepage) {
					// seems like only option to get lineage into the _Live table
			//		$page->publish('Stage', 'Live');
				//}
				$ctr++;
			}
		}

		if ($ctr > 0) {
			DB::alteration_message("Lineage fixed for ".$ctr." pages of class ".$clazzname,"changed");
		}

		// FIXME Live without draft need tweaked also
	}


}
