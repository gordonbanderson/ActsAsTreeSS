<?php

class ActsAsTreeExtensionTest extends SapphireTest {

    public static $fixture_file = "acts_as_tree/tests/acts_as_tree.yml";

    public function setUp() {
        parent::setUp();
        $page = $this->objFromFixture('Page', 'homepage');
        $page->requireDefaultRecords();
    }

    public function testRequireDefaultRecords() {
        error_log('-------------');
        error_log('Testing RDFR');

        //Reset Depth and Lineage as if they had just been created
        $sql = 'UPDATE "SiteTree" SET Lineage=null, Depth=0';
        DB::query($sql);
        $page = new SiteTree();
        $page->requireDefaultRecords();
        foreach (Page::get() as $page) {
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
        $parent = $this->objFromFixture('Page', 'menu040401');
        $page = new Page();
        $page->Title = 'Menu 04040101';
        $page->ParentID = $page->ID;
        $page->write();
        $this->checkPage($page);
    }

	public function testEdit() {
        $page = $this->objFromFixture('Page', 'menu040401');
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
    public function testMove() {
        $page = $this->objFromFixture('Page', 'toplevel04');

        foreach (Page::get() as $page) {
            error_log('BEFORE MOVE: PAGE ' . $page->ID . ': ' . $page->Lineage);
        }

        $newParent = $this->objFromFixture('Page', 'toplevel01');
        $page->ParentID = $newParent->ID;
        $page->write();
        foreach (Page::get() as $page) {
            error_log('AFTER MOVE: PAGE ' . $page->ID . ': ' . $page->Lineage);
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

        $this->assertEquals($depth, $page->Depth);
        $this->assertEquals($lineage, $page->Lineage);

        error_log('Checking depth ' . $page->Depth);
        error_log('Checking lineage ' . $page->Lineage);

    }

}
