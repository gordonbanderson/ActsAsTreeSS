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

    private $oawCtr = 0;

    private $ActsAsTreeNewRecord = false;

    public function onBeforePublish() {
        //$this->owner->Depth = 0;
        //$this->owner->Lineage =null;
        //$this->owner->LineageState = 'Updating';
        error_log('OBP');
    }

    public function onBeforeWrite() {
        if (!$this->owner->ID) {
            error_log('++++ NEW REOCRD ++++');
            $this->ActsAsTreeNewRecord = true;
        }

        parent::onBeforeWrite();
    }

    /*
    Calculate Lineage after write, as ID is required first
     */
    public function onAfterWrite() {
        parent::onAfterWrite();
        $this->oawCtr++;
        error_log("\n\n\n\n" . 'ON AFTER CTR: ' . $this->oawCtr);
        error_log('ON AFTER WRITE: ' . $this->owner->ID);
        error_log('ON AFTER WRITE PID=: ' . $this->owner->ParentID);
        error_log('ON AFTER WRITE LineageState=: ' . $this->owner->LineageState);

        error_log('PARENT CHANGED? ' . $this->owner->isChanged('ParentID'));
        error_log('NEW RECORD? ' . $this->ActsAsTreeNewRecord);


        $this->OldLineage = $this->owner->Lineage;
        if ($this->owner->LineageState != 'Updated' || $this->oawCtr == 3 ||
            (!$this->ActsAsTreeNewRecord && $this->oawCtr == 1)
        ) {
            if ($this->owner->ParentID == 0) {
                $this->owner->Depth = 1;
                $this->owner->Lineage = $this->paddedNumber($this->owner->ID);
            } else {
                $parent = $this->owner->Parent();
                $this->owner->Depth = $parent->Depth + 1;
                error_log('Stetching lineage');
                $this->owner->Lineage = $parent->Lineage . $this->paddedNumber($this->owner->ID);
                error_log('Streteched to ' . $this->owner->Lineage);
            }

            if ($this->OldLineage != $this->owner->Lineage) {
                error_log("\t++++++ Lineage changed, NEW RECORD=" . $this->ActsAsTreeNewRecord);
                if (!$this->ActsAsTreeNewRecord) {
                    error_log("\t\tUPDATE SUBTREE REQUIRED");
                    error_log('OLD LINEAGE:' .$this->OldLineage);
                    $this->updateSubtree($this->OldLineage);
                }
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
        $lenToRemove = strlen($lineageToUpdate);
        // FIXME, sql injection
        $pages = SiteTree::get()
                ->where('"Lineage" LIKE \'' . $lineageToUpdate .'%\'')
                ->sort('Depth');
        error_log('FOUND PAGES TO UPDATE:' , $pages->count());
        $suffix = $this->getVersionSuffix();
        DB::query('begin');
        foreach ($pages as $page) {
            $sql = '';

            error_log('UPDATE REQUIRED: ' .$page->Lineage);
            if ($page->Lineage == $lineageToUpdate) {
                error_log('Skiing page ' . $page->Lineage);
            } else {
                $lineage = substr($page->Lineage, $lenToRemove);
                $lineage = $this->owner->Lineage . $lineage;
                error_log('MOVED LINEAGE: ' . $lineage);
                $page->Lineage = $lineage;

                $sql .= 'UPDATE "SiteTree'.$suffix.'" SET "Lineage" = \''.$lineage.'\'';
                $sql .= ' WHERE "ID"=' . $page->ID . ';';
                $sql .' "\n';
                error_log('SQL:' . $sql);
                DB::query($sql);
            }
        }

        DB::query('commit');
    }


	private function paddedNumber($i) {
		// fixme, use config
		$result = str_pad($i, 5, '0', STR_PAD_LEFT);
		return $result;
	}

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		// FIXME - take account of locales
		DB::query('UPDATE "SiteTree" set "Depth"=1 where "ParentID" = 0;');

		// add depth to comments missing this value
		$maxthreaddepth = 5; //SiteTree::get_config_value('ActsAsTree', 'maximum_thread_comment_depth');

		$clazzname = $this->owner->ClassName;
		$suffixes = array('','_Live');


		for ($i=0; $i <= $maxthreaddepth; $i++) {
			foreach ($suffixes as $suffix) {
				$sql = 'SELECT "ID" FROM '
                     . "\"SiteTree{$suffix}\" WHERE \"Depth\"=" . $i;
                $records = DB::query($sql);
                $ids = array();
                foreach ($records as $record) {
                    array_push($ids, $record['ID']);
                }

                if (sizeof($ids) > 0) {
                    $csvIDs = Convert::raw2sql(implode(',', $ids));
                    $sql = 'UPDATE "SiteTree'.$suffix
                    . "\" SET \"Depth\"=" . ($i+1) . ", \"LineageState\"='Updating' WHERE \"ParentID\" IN ($csvIDs)"
                    . " AND \"Depth\" != " . ($i+1);
                    DB::query($sql);
                }
			}

		}

		$ctr = 0;

		for ($i=0; $i <= $maxthreaddepth; $i++) {
            $pages =  SiteTree::get()->filter('Depth',$i);
			$pages =  SiteTree::get()->filter('Depth',$i)->where('"Lineage" IS NULL');

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

    private function getVersionSuffix() {
        $suffix = '';
        $mode = Versioned::get_reading_mode();
        if($mode == 'Stage.Live') {
                $suffix = '_Live';
        }
        return $suffix;
    }

}
