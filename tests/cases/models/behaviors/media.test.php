<?php
require_once CORE_TEST_CASES.DS.'libs'.DS.'model'.DS.'models.php';
require_once(APP . 'plugins' . DS . 'media' . DS . 'config' . DS . 'core.php');
require_once dirname(__FILE__) . DS . '..' . DS . '..' . DS . '..' . DS . 'fixtures' . DS . 'test_data.php';

class MediaBehaviorTestCase extends CakeTestCase {
	var $fixtures = array('plugin.media.song', 'core.image');

	function start() {
		parent::start();
		
		$this->loadFixtures('Song');
		
		$this->TmpFolder = new Folder(TMP . 'test_suite' . DS, true);
		$this->TmpFolder->create($this->TmpFolder->pwd().'static/img');
		$this->TmpFolder->create($this->TmpFolder->pwd().'static/doc');
		$this->TmpFolder->create($this->TmpFolder->pwd().'static/txt');		
		$this->TmpFolder->create($this->TmpFolder->pwd().'filter');
		$this->TmpFolder->create($this->TmpFolder->pwd().'transfer');
		
		$this->TestData = new MediumTestData();
		$this->file0 = $this->TestData->getFile(array('image-png.png' => $this->TmpFolder->pwd() . 'static/img/image-png.png'));
		$this->file1 = $this->TestData->getFile(array('image-jpg.jpg' => $this->TmpFolder->pwd() . 'static/img/image-jpg.jpg'));
		$this->file2 = $this->TestData->getFile(array('text-plain.txt' => $this->TmpFolder->pwd() . 'static/txt/text-plain.txt'));
	}
	
	function end() {
		parent::end();
		$this->TestData->flushFiles();
		$this->TmpFolder->delete();
		ClassRegistry::flush();
	}	

	function testSetup() {
		$Model =& ClassRegistry::init('Image');
		$this->expectError();
		$Model->Behaviors->attach('Media.Media');
		
		$Model =& ClassRegistry::init('TheVoid');
		$this->expectError();
		$Model->Behaviors->attach('Media.Media');
		
		$Model =& ClassRegistry::init('Song');
		$Model->Behaviors->attach('Media.Media');
	}
	
	function testFind() {
		$Model =& ClassRegistry::init('Song');
		$Model->Behaviors->attach('Media.Media', array('base' => $this->TmpFolder->pwd(),'makeVersions' => false, 'createDirectory' => false, 'metadataLevel' => 1));
		$result = $Model->find('all');
		$this->assertEqual(count($result), 3);

		/* Virtual */
		$result = $Model->findById(1);
		$this->assertTrue(Set::matches('/Song/size', $result));
		$this->assertTrue(Set::matches('/Song/mime_type',$result)); 
	}

	function testSave() {
		$Model =& ClassRegistry::init('Song');
		$Model->Behaviors->attach('Media.Media', array('base' => $this->TmpFolder->pwd(),'makeVersions' => false, 'createDirectory' => false, 'metadataLevel' => 1));
				
		$file = $this->TestData->getFile(array('application-pdf.pdf' => $this->TmpFolder->pwd() . 'static/doc/application-pdf.pdf'));
		$item = array('file' => $file);
		$Model->create();
		$result = $Model->save($item);
		$this->assertTrue($result);
		
		$result = $Model->findById(5);
		$expected = array ( 'Song' => array ( 'id' => '5', 'dirname' => 'static/doc', 'basename' => 'application-pdf.pdf', 'checksum' => 'f7ee91cffd90881f3d719e1bab1c4697', 'size' => 13903, 'mime_type' => 'application/pdf', ), );
		$this->assertEqual($expected, $result);
	}
	
}
?>