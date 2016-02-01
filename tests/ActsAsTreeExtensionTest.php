<?php

class ActsAsTreeExtensionTest extends SapphireTest {

    public static $fixture_file = "acts_as_tree/tests/acts_as_tree.yml";

    public function setUp() {
        parent::setUp();
        $page = $this->objFromFixture('Page', 'homepage');
        $page->requireDefaultRecords();
    }

    public function testRequireDefaultRecords() {
        error_log("\n\n\n\n\n\n");
        error_log('-------------');
        error_log('Testing RDFR');

        //Reset Depth and Lineage as if they had just been created
        $sql = 'DELETE FROM SiteTree';
        DB::query($sql);
        $sql = 'DELETE FROM SiteTree_Live';
        DB::query($sql);
        $page = new SiteTree();
        $page->requireDefaultRecords();
        foreach (Page::get() as $page) {
            error_log('QQ: ' . $page->LineageState);

            $this->checkPage($page);
        }
    }

	public function testCreateTopLevel() {
        $page = new Page();
        $page->Title = 'Top level page';
        $page->write();
        $this->checkPage($page);
	}

    public function testCreateWithAncestry() {
        error_log("\n\n\n\n\n\n");
        error_log('+++++++++++++++++++++++++++++++');
        $parent = $this->objFromFixture('Page', 'menu040401');
        error_log('PARENT LINEAGE: ' . $parent->Lineage);
        $page = new Page();
        $page->Title = 'Menu 04040101';
        $page->ParentID = $parent->ID;
        error_log('SET PARENT ID TO ' . $page->ParentID);
        $page->write();
        $this->checkPage($page);
    }

	public function testEdit() {
        error_log("\n\n\n\n********** TEST CAN EDIT ***********\n\n\n\n");
        foreach (Page::get() as $page) {
            error_log($page->Title. ', ' . $page->ID . ', ParentID ' . $page->ParentID . ', D/L = ' .$page->Depth .'/' . $page->Lineage);
        }

        $page = $this->objFromFixture('Page', 'menu040401');
        error_log('EDIT: Original lineage:' . $page->Lineage);
        $this->checkPage($page);

        $originalDepth = $page->Depth;
        $originalLineage = $page->Lineage;
        $page->Title = 'A different title';
        $page->write();

        $this->checkPage($page);
        $this->assertEquals($originalDepth, $page->Depth);
        $this->assertEquals($originalLineage, $page->Lineage);
    }

    /*
    Child pages need updated as well
     */
    public function testMoveDown() {
        $page = $this->objFromFixture('Page', 'toplevel04');
        error_log('==== MOVING ====');
        error_log('MOVING PAGE ' . $page->ID .', ' . $page->Title);

        foreach (Page::get() as $pageCheck) {
            error_log('BEFORE MOVE: PAGE ' . $pageCheck->ID . ': ' . $pageCheck->Lineage);
        }

        $newParent = $this->objFromFixture('Page', 'toplevel01');
        error_log('SETTING PARENT ID ' . $newParent->ID);
        $page->ParentID = $newParent->ID;

        error_log("REWRITING PAGE " . $page.', ' . $page->Title);
        $page->write();
        foreach (Page::get() as $pageCheck) {
            error_log('AFTER MOVE: PAGE ' . $pageCheck->ID . ': ' . $pageCheck->Lineage);
        }
    }

    private function checkPage($page) {
        error_log($page->Title . ' ID=' . $page->ID .', D=' . $page->Depth . ', L=' .$page->Lineage);
        $depth = 1;
        $lineageIDs = array();
        $hPage = $page;

        // count back in the hierarchy and check depth is correctly calculated
        while ($hPage->ParentID > 0) {
            $depth++;
            array_push($lineageIDs, $hPage->ID);
            $hPage = DataObject::get_by_id('SiteTree', $hPage->ParentID);
        }

        array_push($lineageIDs, $hPage->ID);

        $lineage = '';
        foreach (array_reverse($lineageIDs) as $id) {
            $lineage .= str_pad($id, 5, '0', STR_PAD_LEFT);
        }

error_log('Checking depth ' . $page->Depth);
        error_log('Checking lineage ' . $page->Lineage);
        error_log(print_r($lineageIDs,1));

        $this->assertEquals($depth, $page->Depth);
        $this->assertEquals($lineage, $page->Lineage);


    }

}
