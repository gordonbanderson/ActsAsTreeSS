<?php

class ActsAsTreeExtension extends DataExtension {
	private static $db = array(
		'Depth' => 'Int',
		'Lineage' => 'Varchar(255)',
		'OldLineage' => 'Varchar(255)',
		'SubTreeRequiresUpdate' => 'Boolean',
		'IsLineageDirty' => 'Boolean'
	);

	private static $indexes = array(
		'Depth' => true,
		'Lineage' => true
	);


	private $oawctr = 0;



	public function onBeforeWrite() {
		//error_log("AATE ".$this->owner->ID." OBW PARENT ID=:".$this->owner->ParentID);
		//error_log('T2a: STAGE='.Versioned::get_reading_mode());

		if ($this->owner->isChanged('ParentID',2)) {
			//error_log('***** CHANGE OF PARENT ID FOR ITEM '.$this->owner->Title.' *****');
			$this->owner->SubTreeRequiresUpdate = true;
			$this->owner->OldLineage = $this->owner->Lineage;
			$this->owner->IsLineageDirty = true;
		}

		parent::onBeforeWrite();
	}



	public function onAfterWrite() {

		//error_log("AATE".$this->owner->ID." OAW PARENT ID=:".$this->owner->ParentID);

		$this->oawctr++;


		//error_log('++++ ON AFTER WRITE, LINEAGE FIXED=:'.$this->owner->LineageFixed.', ctr='.$this->oawctr);

		if (!($this->owner->LineageFixed) || ($this->oawctr == 3)) {
			//error_log('++++ FIXING LINEAGE T1 ++++');
			// Calculate depth and lineage from parent comment
			if ($this->owner->ParentID == 0) {
				$this->owner->Depth = 1;
				//error_log('++++ FIXING LINEAGE T2 ++++');

				$this->owner->Lineage = $this->paddedNumber($this->owner->ID);
			} else {
				//error_log('++++ FIXING LINEAGE T3 ++++');

				$pc = $this->owner->Parent();
				//error_log('++++ Parent->ID = '.$pc->ID);
				//error_log('++++ PARENT ID '.$this->owner->ParentID);
				$this->Depth = $pc->owner->Depth + 1;
				$this->owner->Lineage = ($pc->Lineage).$this->paddedNumber($this->owner->ID);
				//error_log('++++ LINEAGE SET TO '.$this->owner->Lineage);
			}
			$this->owner->LineageFixed = true;
			$this->owner->IsLineageDirty = false;
			//error_log('>>>> ABOUT TO WRITE TO DB');
			$result = $this->owner->write();
			//error_log('>>>> OBJECT WRITTEN TO DB? '.$result);
		}

		if ($this->owner->OldLineage) {
			//error_log('%%%%% OLD LINEAGE FOUND, SUBTREE NEEDS TWEAKED ');
		}

		parent::onAfterWrite();


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


	private function paddedNumber($i) {
		// fixme, use config
		$result = str_pad($i, 5, '0', STR_PAD_LEFT);
		//error_log("Padding ".$i." => ".$result);
		return $result;
	}
	

	/**
	 * Migrates the old {@link PageComment} objects to {@link Comment}
	 */
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
				$sql = "UPDATE SiteTree{$suffix} c1\n".
				"INNER JOIN SiteTree{$suffix} c2\n".
				"ON c1.ID = c2.ParentID\n".
				"SET c2.Depth=".($i+1)." WHERE c1.Depth=".$i." AND c2.ClassName='".$clazzname."';";
				DB::query($sql);
				//error_log($sql);
			}
			
		}

		$ctr = 0;

		for ($i=0; $i <= $maxthreaddepth; $i++) {
			$pages =  SiteTree::get()->filter('Depth',$i)->where("Lineage IS NULL AND ClassName = '".$clazzname."'");

			foreach ($pages as $page) {
				// write the page, this will update the lineage
				$page->write();

				// check for a live version and publish the draft version if a live page exists
				$livepage = Versioned::get_by_stage('SiteTree', 'Live')->byID($page->ID);
				if ($livepage) {
					// seems like only option to get lineage into the _Live table
					$page->publish('Stage', 'Live');
				}
				$ctr++;
			}
		}
		
		if ($ctr > 0) {
			DB::alteration_message("Lineage fixed for ".$ctr." pages of class ".$clazzname,"changed");
		}

		// FIXME Live without draft need tweaked also
	}


}