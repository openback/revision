<?php
class RevisionPost extends CakeTestModel {
    public $name = 'RevisionPost';
    public $alias = 'Post';
	public $actsAs = array('Revision.Revision' => array('limit' => 5));

	public function beforeUndelete() {
		$this->beforeUndelete = true;
		return true;
	}

	public function afterUndelete() {
		$this->afterUndelete = true;
		return true;
	}
}

class RevisionArticle extends CakeTestModel {
    public $name = 'RevisionArticle';
    public $alias = 'Article';
	public $actsAs = array(
		'Tree',
		'Revision.Revision' => array('ignore' => array('title'))
	);

	/**
	 * Example of using this callback to undelete children
	 * of a deleted node.
	 */
	public function afterUndelete() {
		$former_children = $this->ShadowModel->find('list', array(
			'conditions' => array(
				'parent_id' => $this->id
			),
			'distinct' => true,
			'order' => 'version_created DESC, version_id DESC' 
		));
		foreach (array_keys($former_children) as $cid) {
			$this->id = $cid;
			$this->undelete();
		}
	}
}

class RevisionUser extends CakeTestModel {
    public $name = 'RevisionUser';
    public $alias = 'User';
	public $actsAs = array('Revision.Revision');
}

class RevisionComment extends CakeTestModel {
    public $name = 'RevisionComment';
    public $alias = 'Comment';
	public $actsAs = array('Containable','Revision.Revision');

	public $hasMany = array(
		'Vote' => array(
			'className' => 'RevisionVote',
			'foreignKey' => 'revision_comment_id',
			'dependent' => true
		)
	);
}

class RevisionVote extends CakeTestModel {
    public $name = 'RevisionVote';
    public $alias = 'Vote';
	public $actsAs = array('Revision.Revision');
}

class RevisionTag extends CakeTestModel {
    public $name = 'RevisionTag';
    public $alias = 'Tag';
	public $actsAs = array('Revision.Revision');
	public $hasAndBelongsToMany = array(
		'Comment' => array(
			'className' => 'RevisionComment'
		)
	);
}

class CommentsTag extends CakeTestModel {
    public $name = 'CommentsTag';
    public $useTable = 'revision_comments_revision_tags';
	public $actsAs = array('Revision');
}

class RevisionTestCase extends CakeTestCase {

	public $autoFixtures = false;
	public $fixtures = array(
		'plugin.revision.revision_article', 
		'plugin.revision.revision_articles_rev', 
		'plugin.revision.revision_post', 
		'plugin.revision.revision_posts_rev', 
		'plugin.revision.revision_user',
		'plugin.revision.revision_comment',
		'plugin.revision.revision_comments_rev',
		'plugin.revision.revision_vote',
		'plugin.revision.revision_votes_rev',
		'plugin.revision.revision_comments_revision_tag',
		'plugin.revision.revision_comments_revision_tags_rev',
		'plugin.revision.revision_tag',
		'plugin.revision.revision_tags_rev'
	);

	public function startTest($method = null) {
		parent::startTest($method);
	}

	public function endTest($method = null) {
		ClassRegistry::flush();
		parent::endTest($method);
	}

	public function testSavePost() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('title' => 'New Post', 'content' => 'First post!'));
		$Post->save($data);
		$Post->id = 4;
		$result = $Post->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Post' => array(
				'id' => 4, 
				'title' => 'New Post', 
				'content' => 'First post!', 
				'version_id' => 4
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testSaveWithoutChange() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->id = 1;
		$revision = $Post->createRevision();
        $this->assertTrue(!empty($revision));

		$Post->id = 1;
		$count = $Post->ShadowModel->find('count', array('conditions'=>array('id'=>1)));
		$this->assertEqual($count,2);

		$Post->id = 1;
		$data = $Post->read();
		$Post->save($data);

		$Post->id = 1;
		$count = $Post->ShadowModel->find('count', array('conditions'=>array('id'=>1)));
		$this->assertEqual($count,2);
	}

	public function testEditPost() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array( 'title' => 'New Post'));
		$Post->create();
		$Post->save($data);
		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Post' => array(
				'id' => 1, 
				'title' => 'Edited Post', 
				'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
				'version_id' => 5
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testShadow() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->create(array('Post' => array('title' => 'Non Used Post' , 'content' => 'Whatever')));
		$Post->save();
		$post_id = $Post->id;

		$Post->create(array('Post' => array('title' => 'New Post 1' , 'content' => 'nada')));
		$Post->save();

		$Post->save(array('Post' => array('id'=>5, 'title' => 'Edit Post 2')));

		$Post->save(array('Post' => array('id'=>5, 'title' => 'Edit Post 3')));

		$result = $Post->ShadowModel->find('first',array('fields' => array('version_id','id','title','content')));
		$expected = array( 
			'Post' => array(
	            'version_id' => 7,
	            'id' => 5,
	            'title' => 'Edit Post 3',
	            'content' => 'nada'
	        )
		);
		$this->assertEqual($expected, $result);

		$Post->id = $post_id;
		$result = $Post->newest();
		$this->assertEqual($result['Post']['title'],'Non Used Post');
		$this->assertEqual($result['Post']['version_id'],4);

		$result = $Post->ShadowModel->find('first',array(
			'conditions' => array('version_id'=>4),
			'fields' => array('version_id','id','title','content')));

		$expected = array( 
			'Post' => array(
	            'version_id' => 4,
	            'id' => 4,
	            'title' => 'Non Used Post',
	            'content' => 'Whatever'
	        )
		);
		$this->assertEqual($expected, $result);
	}

	public function testCurrentPost() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post'));
		$Post->save($data);

		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Re-edited Post'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Post' => array(
				'id' => 1, 
				'title' => 'Re-edited Post', 
				'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
				'version_id' => 5
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testRevisionsPost() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post'));
		$Post->save($data);

		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Re-edited Post'));
		$Post->save($data);
		$Post->create();
		$data = array('Post' => array('id'=>1, 'title' => 'Newest edited Post'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->revisions(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array( 
			0 => array(
				'Post' => array(
					'id' => 1, 
					'title' => 'Re-edited Post', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 5
				)
			),
			1 => array (
				'Post' => array(
					'id' => 1, 
					'title' => 'Edited Post', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 4
				),
			),
			2 => array (
				'Post' => array(
					'id' => 1, 
					'title' => 'Lorem ipsum dolor sit amet', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 1
				),
			)
		);
		$this->assertEqual($expected, $result);

		$Post->id = 1;
		$result = $Post->revisions(array('fields' => array('id', 'title', 'content', 'version_id')),true);
		$expected = array( 
			0 => array(
				'Post' => array(
					'id' => 1, 
					'title' => 'Newest edited Post', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 6
				)
			),
			1 => array(
				'Post' => array(
					'id' => 1, 
					'title' => 'Re-edited Post', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 5
				)
			),
			2 => array (
				'Post' => array(
					'id' => 1, 
					'title' => 'Edited Post', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 4
				),
			),
			3 => array (
				'Post' => array(
					'id' => 1, 
					'title' => 'Lorem ipsum dolor sit amet', 
					'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
					'version_id' => 1
				),
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testDiff() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 1'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 2'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 3'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->diff(null,null,array('fields'=>array('version_id','id', 'title', 'content')));
		$expected = array(
			'Post' => array(
				'version_id' => array(6,5,4,1),
				'id' => 1,
				'title' => array(
					'Edited Post 3',
					'Edited Post 2',
					'Edited Post 1',
					'Lorem ipsum dolor sit amet'
				),
				'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.'
			)
		);
		$this->assertEqual($expected, $result);
	}
    
	public function testDiffMultipleFields() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('id'=>1, 'title' => 'Edited title 1'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'content' => 'Edited content'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited title 2'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->diff(null,null,array('fields'=>array('version_id','id', 'title', 'content')));
		$expected = array(
			'Post' => array(
				'version_id' => array(6,5,4,1),
				'id' => 1,
				'title' => array(
					0 => 'Edited title 2',
					2 => 'Edited title 1',
					3 => 'Lorem ipsum dolor sit amet'
				),
				'content' => array(
                        1 => 'Edited content',
                        3 => 'Lorem ipsum dolor sit amet, aliquet feugiat.'
                )
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testPrevious() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->id = 1;
		$this->assertNull($Post->previous());

		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 2'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 3'));
		$Post->save($data);

		$Post->id = 1;
		$result = $Post->previous(array('fields'=>array('version_id','id','title')));
		$expected = array(
			'Post' => array(
				'version_id' => 4,
				'id' => 1,
				'title' => 'Edited Post 2'
			)
		); 
		$this->assertEqual($expected, $result);
	}

	public function testUndoEdit() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 1'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 2'));
		$Post->save($data);
		$data = array('Post' => array('id'=>1, 'title' => 'Edited Post 3'));
		$Post->save($data);

		$Post->id = 1;
		$success = $Post->undo();
		$this->assertTrue(!empty($success));

		$result = $Post->find('first', array('fields'=>array('id', 'title', 'content')));
		$expected = array(
			'Post' => array(
				'id' => 1,
				'title' =>'Edited Post 2',
				'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.'
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testUndoCreate() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->create(array('Post' => array('title' => 'New post','content' => 'asd')));
		$Post->save();

		$result = $Post->read();
		$this->assertEqual($result['Post']['title'],'New post');
		$id = $Post->id;

		$Post->undo();

		$Post->id = $id;
		$this->assertFalse($Post->read());

		$Post->undelete();
		$result = $Post->read();
		$this->assertEqual($result['Post']['title'],'New post');

	}

	public function testRevertTo() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->save(array('Post' => array('id'=>1, 'title' => 'Edited Post 1')));
		$Post->save(array('Post' => array('id'=>1, 'title' => 'Edited Post 2')));
		$Post->save(array('Post' => array('id'=>1, 'title' => 'Edited Post 3')));

		$Post->id = 1;
		$result = $Post->previous();
		$this->assertEqual($result['Post']['title'],'Edited Post 2');

		$version_id = $result['Post']['version_id'];

		$revert = $Post->revertTo($version_id);
		$this->assertTrue(!empty($revert));

		$result = $Post->find('first', array('fields'=>array('id', 'title', 'content')));
		$this->assertEqual($result['Post']['title'],'Edited Post 2');
	}

	public function testLimit() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 1'));
		$Post->save($data);
		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 2'));
		$Post->save($data);
		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 3'));
		$Post->save($data);
		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 4'));
		$Post->save($data);
		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 5'));
		$Post->save($data);
		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 6'));
		$Post->save($data);


		$data = array('Post' => array('id' => 2, 'title' => 'Edited Post 6'));
		$Post->save($data);

		$Post->id = 2;

		$result = $Post->revisions(array('fields' => array('id', 'title', 'content', 'version_id')),true);
		$expected = array( 
			0 => array(
				'Post' => array(
					'id' => 2, 
					'title' => 'Edited Post 6', 
					'content' => 'Lorem ipsum dolor sit.', 
					'version_id' => 9
				)
			),
			1 => array (
				'Post' => array(
					'id' => 2, 
					'title' => 'Edited Post 5', 
					'content' => 'Lorem ipsum dolor sit.', 
					'version_id' => 8
				),
			),
			2 => array(
				'Post' => array(
					'id' => 2, 
					'title' => 'Edited Post 4', 
					'content' => 'Lorem ipsum dolor sit.', 
					'version_id' => 7
				)
			),
			3 => array (
				'Post' => array(
					'id' => 2, 
					'title' => 'Edited Post 3', 
					'content' => 'Lorem ipsum dolor sit.', 
					'version_id' => 6
				),
			),
			4 => array(
				'Post' => array(
					'id' => 2, 
					'title' => 'Edited Post 2', 
					'content' => 'Lorem ipsum dolor sit.', 
					'version_id' => 5
				)
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testTree() {
		$this->loadFixtures('RevisionArticle','RevisionArticlesRev');

		$Article = new RevisionArticle();

		$Article->initializeRevisions();

		$Article->save(array('Article' => array('id'=>3, 'content' => 'Re-edited Article')));
		$this->assertNoErrors('Save() with tree problem : %s');

		$Article->moveUp(3);
		$this->assertNoErrors('moveUp() with tree problem : %s');

		$Article->id = 3;
		$result = $Article->newest(array('fields' => array('id', 'version_id')));
		$this->assertEqual($result['Article']['version_id'], 4);

		$Article->create(array('title'=>'midten','content'=>'stuff','parent_id'=>2));
		$Article->save();
		$this->assertNoErrors('Save() with tree problem : %s');

		$result = $Article->find('all', array('fields'=>array('id','lft','rght','parent_id')));
		$expected = array('id'=>1, 'lft'=>1, 'rght'=>8, 'parent_id'=>null);
		$this->assertEqual($result[0]['Article'],$expected);
		$expected = array('id' => 2, 'lft'=>4, 'rght'=>7, 'parent_id'=>1);
		$this->assertEqual($result[1]['Article'],$expected);
		$expected = array('id'=>3, 'lft'=>2, 'rght'=>3, 'parent_id'=>1);
		$this->assertEqual($result[2]['Article'],$expected);
		$expected = array('id'=>4, 'lft'=>5, 'rght'=>6, 'parent_id'=>2);
		$this->assertEqual($result[3]['Article'],$expected);
	}

	public function testIgnore() {
		$this->loadFixtures('RevisionArticle','RevisionArticlesRev');

		$Article = new RevisionArticle();

		$data = array('Article' => array('id'=>3, 'title' =>'New title', 'content' => 'Edited'));
		$Article->save($data);
		$data = array('Article' => array('id'=>3, 'title' => 'Re-edited title'));
		$Article->save($data);

		$Article->id = 3;
		$result = $Article->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Article' => array(
				'id' => 3, 
				'title' => 'New title', 
				'content' => 'Edited', 
				'version_id' => 1
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testWithoutShadowTable() {
		$this->loadFixtures('RevisionUser');

		$User = new RevisionUser();

		$data = array('User' => array('id'=>1, 'name' =>'New name'));
		$success = $User->save($data);
		$this->assertNoErrors();
		$this->assertTrue(!empty($success));
	}


	public function testRevertToDate() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$data = array('Post' => array('id'=>3, 'title' => 'Edited Post 6'));
		$Post->save($data);

		$revertToYesterday = $Post->revertToDate(date('Y-m-d H:i:s',strtotime('yesterday')));
		$this->assertTrue(!empty($revertToYesterday));
		$result = $Post->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Post' => array(
					'id' => 3, 
					'title' => 'Post 3', 
					'content' => 'Lorem ipsum dolor sit.',
					'version_id' => 5
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testCascade() {
		$this->loadFixtures('RevisionComment','RevisionCommentsRev','RevisionVote','RevisionVotesRev');

		$Comment = new RevisionComment();

		$original_comments = $Comment->find('all');

		$data = array('Vote' => array('id'=>3, 'title' => 'Edited Vote','revision_comment_id'=>1));
		$Comment->Vote->save($data);

		$revert = $Comment->Vote->revertToDate('2008-12-09');
		$this->assertTrue(!empty($revert));
		$Comment->Vote->id = 3;
		$result = $Comment->Vote->newest(array('fields' => array('id', 'title', 'content', 'version_id')));

		$expected = array(
			'Vote' => array(
					'id' => 3, 
					'title' => 'Stuff', 
					'content' => 'Lorem ipsum dolor sit.',
					'version_id' => 5
			)
		);

		$this->assertEqual($expected, $result);

		$data = array('Comment' => array('id' => 2, 'title' => 'Edited Comment'));
		$Comment->save($data);

		$revert = $Comment->revertToDate('2008-12-09');
		$this->assertTrue(!empty($revert));

		$reverted_comments = $Comment->find('all');

		$this->assertEqual($original_comments, $reverted_comments);
	}

	public function testCreateRevision() {
		$this->loadFixtures('RevisionArticle','RevisionArticlesRev');

		$Article = new RevisionArticle();

		$data = array('Article' => array('id'=>3, 'title' =>'New title', 'content' => 'Edited'));
		$Article->save($data);
		$data = array('Article' => array('id'=>3, 'title' => 'Re-edited title'));
		$Article->save($data);

		$Article->id = 3;
		$result = $Article->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Article' => array(
				'id' => 3, 
				'title' => 'New title', 
				'content' => 'Edited', 
				'version_id' => 1
			)
		);
		$this->assertEqual($expected, $result);

		$Article->id = 3;
		$createRevision = $Article->createRevision();
		$this->assertTrue(!empty($createRevision));
		$result = $Article->newest(array('fields' => array('id', 'title', 'content', 'version_id')));
		$expected = array(
			'Article' => array(
				'id' => 3, 
				'title' => 'Re-edited title', 
				'content' => 'Edited', 
				'version_id' => 2
			)
		);
		$this->assertEqual($expected, $result);

	}

	public function testUndelete() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->id = 3;
		$result = $Post->undelete();
		$this->assertFalse($result);

		$Post->delete(3);

		$result = $Post->find('count',array('conditions'=>array('id'=>3)));
		$this->assertEqual($result,0);

		$Post->id = 3;
		$Post->undelete();

		$result = $Post->find('first',array('conditions'=>array('id'=>3),'fields' => array('id', 'title', 'content')));

		$expected = array(
			'Post' => array(
					'id' => 3,
					'title' => 'Post 3', 
					'content' => 'Lorem ipsum dolor sit.'
			)
		);
		$this->assertEqual($expected, $result);

	}

	public function testUndeleteCallbacks() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->id = 3;
		$result = $Post->undelete();
		$this->assertFalse($result);

		$Post->delete(3);

		$result = $Post->find('first',array('conditions'=>array('id'=>3)));
		$this->assertFalse($result);

		$Post->id = 3;
		$this->assertTrue($Post->undelete());
		$this->assertTrue($Post->beforeUndelete);
		$this->assertTrue($Post->afterUndelete);

		$result = $Post->find('first',array('conditions'=>array('id'=>3)));

		$expected = array(
			'Post' => array(
					'id' => 3,
					'title' => 'Post 3', 
					'content' => 'Lorem ipsum dolor sit.',
			)
		);
		$this->assertEqual($expected, $result);
		$this->assertNoErrors();
	}

	public function testUndeleteTree1() {
		$this->loadFixtures(
			'RevisionArticle','RevisionArticlesRev'
		);

		$Article = new RevisionArticle();

		$Article->initializeRevisions();

		$Article->delete(3);

		$Article->id = 3;
		$Article->undelete();

		$result = $Article->find('all');
		$this->assertEqual(sizeof($result),3);
		$this->assertEqual($result[0]['Article']['lft'], 1);
		$this->assertEqual($result[0]['Article']['rght'],6);

		$this->assertEqual($result[1]['Article']['lft'], 2);
		$this->assertEqual($result[1]['Article']['rght'],3);

		$this->assertEqual($result[2]['Article']['id'],  3);
		$this->assertEqual($result[2]['Article']['lft'], 4);
		$this->assertEqual($result[2]['Article']['rght'],5);
	}

	public function testUndeleteTree2() {
		$this->loadFixtures(
			'RevisionArticle','RevisionArticlesRev'
		);

		$Article = new RevisionArticle();

		$Article->initializeRevisions();

		$Article->create(array('title'=>'første barn','content'=>'stuff','parent_id'=>3,'user_id'=>1));
		$Article->save();
		$Article->create(array('title'=>'andre barn','content'=>'stuff','parent_id'=>4,'user_id'=>1));
		$Article->save();

		$Article->delete(3);

		$Article->id = 3;
		$Article->undelete();

		$result = $Article->find('all');
		// Test that children are also "returned" to their undeleted father
		$this->assertEqual(sizeof($result),5);
		$this->assertEqual($result[0]['Article']['lft'], 1);
		$this->assertEqual($result[0]['Article']['rght'],10);

		$this->assertEqual($result[1]['Article']['lft'], 2);
		$this->assertEqual($result[1]['Article']['rght'],3);

		$this->assertEqual($result[2]['Article']['id'],  3);
		$this->assertEqual($result[2]['Article']['lft'], 4);
		$this->assertEqual($result[2]['Article']['rght'],9);

		$this->assertEqual($result[3]['Article']['id'],  4);
		$this->assertEqual($result[3]['Article']['lft'], 5);
		$this->assertEqual($result[3]['Article']['rght'],8);

		$this->assertEqual($result[4]['Article']['id'],  5);
		$this->assertEqual($result[4]['Article']['lft'], 6);
		$this->assertEqual($result[4]['Article']['rght'],7);
	}

	public function testInitializeRevisionsWithLimit() {
		$this->loadFixtures(
			'RevisionPost','RevisionPostsRev',
			'RevisionArticle','RevisionArticlesRev',
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag',
			'RevisionVote','RevisionVotesRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();
		$Post = new RevisionPost();
		$Article = new RevisionArticle();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array('className' => 'RevisionTag','with'=>'CommentsTag'))),false);

		$this->assertFalse($Post->initializeRevisions());
		$this->assertTrue($Article->initializeRevisions());
		$this->assertFalse($Comment->initializeRevisions());
		$this->assertFalse($Comment->Vote->initializeRevisions());
		$this->assertFalse($Comment->Tag->initializeRevisions());
	}

	public function testInitializeRevisions() {
		$this->loadFixtures('RevisionPost');

		$Post = new RevisionPost();

		$this->assertTrue($Post->initializeRevisions(2));

		$result = $Post->ShadowModel->find('all');

		$this->assertEqual(sizeof($result),3);
	}

	public function testRevertAll() {
		$this->loadFixtures(
			'RevisionPost','RevisionPostsRev'
		);

		$Post = new RevisionPost();

		$Post->save(array('id'=>1,'title' => 'tullball1'));
		$Post->save(array('id'=>3,'title' => 'tullball3'));
		$Post->create(array('title' => 'new post','content'=>'stuff'));
		$Post->save();

		$result = $Post->find('all');
		$this->assertEqual($result[0]['Post']['title'],'tullball1');
		$this->assertEqual($result[1]['Post']['title'],'Post 2');
		$this->assertEqual($result[2]['Post']['title'],'tullball3');
		$this->assertEqual($result[3]['Post']['title'],'new post');

		$this->assertTrue( $Post->revertAll(array(
				'date' => date('Y-m-d H:i:s', strtotime('yesterday'))
			))
		);

		$result = $Post->find('all');
		$this->assertEqual($result[0]['Post']['title'],'Lorem ipsum dolor sit amet');
		$this->assertEqual($result[1]['Post']['title'],'Post 2');
		$this->assertEqual($result[2]['Post']['title'],'Post 3');
		$this->assertEqual(sizeof($result),3);
	}

	public function testRevertAllConditions() {
		$this->loadFixtures(
			'RevisionPost','RevisionPostsRev'
		);

		$Post = new RevisionPost();

		$Post->save(array('id'=>1,'title' => 'tullball1'));
		$Post->save(array('id'=>3,'title' => 'tullball3'));
		$Post->create();
		$Post->save(array('title' => 'new post','content'=>'stuff'));

		$result = $Post->find('all');
		$this->assertEqual($result[0]['Post']['title'],'tullball1');
		$this->assertEqual($result[1]['Post']['title'],'Post 2');
		$this->assertEqual($result[2]['Post']['title'],'tullball3');
		$this->assertEqual($result[3]['Post']['title'],'new post');

		$this->assertTrue( $Post->revertAll(array(
				'conditions' => array('Post.id' =>array(1,2,4)),
				'date' => date('Y-m-d H:i:s', strtotime('yesterday'))
			))
		);

		$result = $Post->find('all');
		$this->assertEqual($result[0]['Post']['title'],'Lorem ipsum dolor sit amet');
		$this->assertEqual($result[1]['Post']['title'],'Post 2');
		$this->assertEqual($result[2]['Post']['title'],'tullball3');
		$this->assertEqual(sizeof($result),3);
	}

	public function testOnWithModel() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag',
					'with' => 'CommentsTag'
				)
			)
		),false);
		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$this->assertEqual($result['Tag'][0]['title'],'Fun');
		$this->assertEqual($result['Tag'][1]['title'],'Hard');
		$this->assertEqual($result['Tag'][2]['title'],'Trick');
	}

	public function testHABTMRelatedUndoed() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag',
					'with' => 'CommentsTag'
				)
			)
		),false);
		$Comment->Tag->id = 3;
		$Comment->Tag->undo();
		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual($result['Tag'][2]['title'],'Tricks');
	}

	public function testOnWithModelUndoed() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag',
					'with' => 'CommentsTag'
				)
			)
		),false);
		$Comment->CommentsTag->delete(3);
		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),2);
		$this->assertEqual($result['Tag'][0]['title'],'Fun');
		$this->assertEqual($result['Tag'][1]['title'],'Hard');

		$Comment->CommentsTag->id = 3;
		$this->assertTrue($Comment->CommentsTag->undelete(), 'Undelete unsuccessful');

		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$this->assertEqual($result['Tag'][0]['title'],'Fun');
		$this->assertEqual($result['Tag'][1]['title'],'Hard');
		$this->assertEqual($result['Tag'][2]['title'],'Trick');
		$this->assertNoErrors('Third Tag not back : %s');
	}

	public function testHabtmRevSave() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(
			array('hasAndBelongsToMany' => array('Tag' => array('className' => 'RevisionTag'))),
			false
		);

		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));

		$expected = json_encode($result['Tag']);
		$Comment->id = 1;
		$result = $Comment->newest();
		$this->assertEqual($expected, $result['Comment']['Tag']);

		$Comment->save(array('Comment' => array('id' => 1), 'Tag' => array('Tag' => array(2,4))));

		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),2);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Hard', 'News'));

		$expected = json_encode($result['Tag']);
		$Comment->id = 1;
		$result = $Comment->newest();
		$this->assertEqual(4,$result['Comment']['version_id']);
		$this->assertEqual($expected,$result['Comment']['Tag']);
	}

	public function testHabtmRevCreate() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag'
				)
			)
		),false);

		$result = $Comment->find('first', array('contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));

		$Comment->create(
			array(
				'Comment' => array('title' => 'Comment 4'),
				'Tag' => array(
					'Tag' => array(2,4)
				)
			)
		);

		$Comment->save();

		$result = $Comment->newest();
		$this->assertEqual(json_encode(array(2,4)), $result['Comment']['Tag']);
	}

	public function testHabtmRevIgnore() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->Behaviors->detach('Revision');
		$Comment->Behaviors->attach('Revision', array('ignore'=>array('Tag')));

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag'
				)
			)
		),false);

		$Comment->id = 1;
		$original_result = $Comment->newest();

		$Comment->save(
			array(
				'Comment' => array('id' => 1),
				'Tag' => array(
					'Tag' => array(2,4)
				)
			)
		);

		$result = $Comment->newest();
		$this->assertEqual($original_result, $result);
	}


	public function testHabtmRevUndo() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(
			array('hasAndBelongsToMany' => array('Tag' => array('className' => 'RevisionTag'))),
			false
		);

		$Comment->save(
			array(
				'Comment' => array('id' => 1,'title'=>'edit'),
				'Tag' => array('Tag' => array(2,4))
			)
		);

		$Comment->id = 1;
		$Comment->undo();
		$result = $Comment->find('first', array('recursive'=>1));   //'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));
		$this->assertNoErrors('3 tags : %s');
	}

	public function testHabtmRevUndoJustHabtmChanges() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag'
				)
			)
		),false);

		$Comment->save(
			array(
				'Comment' => array('id' => 1),
				'Tag' => array(
					'Tag' => array(2,4)
				)
			)
		);

		$Comment->id = 1;
		$Comment->undo();
		$result = $Comment->find('first', array('recursive'=>1));   //'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));
		$this->assertNoErrors('3 tags : %s');
	}

	public function testHabtmRevRevert() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag'
				)
			)
		),false);

		$Comment->save(
			array(
				'Comment' => array('id' => 1),
				'Tag' => array(
					'Tag' => array(2,4)
				)
			)
		);

		$Comment->id = 1;
		$Comment->revertTo(1);

		$result = $Comment->find('first', array('recursive'=>1));   //'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));
		$this->assertNoErrors('3 tags : %s');
	}

	public function testRevertToHabtm2() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array(
					'className' => 'RevisionTag'
				)
			)
		),false);

		$comment_one = $Comment->find('first', array('conditions'=>array('Comment.id'=>1),'contain'=>'Tag'));
		$this->assertEqual($comment_one['Comment']['title'],'Comment 1');
		$this->assertEqual(Set::extract($comment_one,'Tag.{n}.id') , array(1,2,3));
		$Comment->id = 1;
		$rev_one = $Comment->newest();
		$this->assertEqual($rev_one['Comment']['title'],'Comment 1');

		$revOneTag = json_decode($rev_one['Comment']['Tag'], true);
		$tagsIds = Set::extract('/id', $revOneTag);
		sort($tagsIds);
		$this->assertEqual($tagsIds , array(1,2,3));
		$version_id = $rev_one['Comment']['version_id'];

		$Comment->create(array('Comment'=>array('id'=>1,'title'=>'Edited')));
		$Comment->save();

		$comment_one = $Comment->find('first', array('conditions'=>array('Comment.id'=>1),'contain'=>'Tag'));
		$this->assertEqual($comment_one['Comment']['title'],'Edited');
		$result = Set::extract($comment_one,'Tag.{n}.id');
		$expected = array(1,2,3);
		$this->assertEqual($result,$expected);
		$Comment->id = 1;
		$rev_one = $Comment->newest();
		$this->assertEqual($rev_one['Comment']['title'],'Edited');
		$revOneTag = json_decode($rev_one['Comment']['Tag'], true);
		$tagsIds = Set::extract('/id', $revOneTag);
		sort($tagsIds);
		$this->assertEqual($tagsIds , array(1,2,3));

		$Comment->revertTo(1);

		$comment_one = $Comment->find('first', array('conditions'=>array('Comment.id'=>1),'contain'=>'Tag'));
		$this->assertEqual($comment_one['Comment']['title'],'Comment 1');

		$tagIds = Set::extract('/Tag/id', $comment_one);
		sort($tagIds);
		$this->assertEqual($tagIds , array(1,2,3));
		$Comment->id = 1;
		$rev_one = $Comment->newest();
		$this->assertEqual($rev_one['Comment']['title'],'Comment 1');
		$revOneTag = json_decode($rev_one['Comment']['Tag'], true);
		$tagsIds = Set::extract('/id', $revOneTag);
		sort($tagsIds);
		$this->assertEqual($tagsIds , array(1,2,3));
	}

	public function testHabtmRevRevertToDate() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array(
			'hasAndBelongsToMany' => array('Tag' => array('className' => 'RevisionTag'))),
			false
		);

		$Comment->save(array('Comment' => array('id' => 1), 'Tag' => array('Tag' => array(2,4))));

		$Comment->id = 1;
		$Comment->revertToDate(date('Y-m-d H:i:s',strtotime('yesterday')));

		$result = $Comment->find('first', array('recursive'=>1));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Hard', 'Trick'));
		$this->assertNoErrors('3 tags : %s');
	}

	public function testRevertToTheTagsCommentHadBefore() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array('className' => 'RevisionTag'))),false);

		$result = $Comment->find('first', array(
			'conditions' => array('Comment.id' => 2),
			'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),2);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Trick'));

		$Comment->save(
			array(
				'Comment' => array('id' => 2),
				'Tag' => array(
					'Tag' => array(2,3,4)
				)
			)
		);

		$result = $Comment->find('first', array(
			'conditions' => array('Comment.id' => 2),
			'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),3);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Hard', 'News', 'Trick'));

		// revert Tags on comment logic
		$Comment->id = 2;
		$revertToYesterday = $Comment->revertToDate(date('Y-m-d H:i:s',strtotime('yesterday')));
		$this->assertTrue(!empty($revertToYesterday),'revertHabtmToDate unsuccessful : %s');

		$result = $Comment->find('first', array(
			'conditions' => array('Comment.id' => 2),
			'contain' => array('Tag' => array('id','title'))));
		$this->assertEqual(sizeof($result['Tag']),2);
		$titles = Set::extract('/Tag/title', $result);
		sort($titles);
		$this->assertEqual($titles, array('Fun', 'Trick'));

	}

	public function testSaveWithOutTags() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(array('hasAndBelongsToMany' => array(
				'Tag' => array('className' => 'RevisionTag'))),false);
    
	    $Comment->id = 1;
	    $newest = $Comment->newest();

	    $Comment->save(array(
	      'Comment' => array('id'=>1,'title'=>'spam')
	    ));
	    
	    $result = $Comment->newest();
	    $this->assertEqual($newest['Comment']['Tag'], $result['Comment']['Tag']);
	}

	public function testRevertToDeletedTag() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->bindModel(
			array(
				'hasAndBelongsToMany' => array(
					'Tag' => array('className' => 'RevisionTag','with'=>'CommentsTag')
				)
			),
			false
		);

		$Comment->Tag->delete(1);

		$result = $Comment->ShadowModel->find('all', array(
			'conditions' => array('version_id' => array(4,5))
		));
		$res0Tag = json_decode($result[0]['Comment']['Tag'], true);
		$tagsIds = Set::extract('/id', $res0Tag);
		sort($tagsIds);
		$this->assertEqual($tagsIds , array('3'));
		$res1Tag = json_decode($result[1]['Comment']['Tag'], true);
		$tagsIds = Set::extract('/id', $res1Tag);
		sort($tagsIds);
		$this->assertEqual($tagsIds , array('2', '3'));
	}

	public function testBadKittyForgotId() {
		$Comment = new RevisionComment();

		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->createRevision(),'createRevision() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->diff(),'diff() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->undelete(),'undelete() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->undo(),'undo() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->newest(),'newest() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->oldest(),'oldest() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->previous(),'previous() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->revertTo(10),'revertTo() : %s');
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->revertToDate(date('Y-m-d H:i:s',strtotime('yesterday')),'revertTo() : %s'));
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($Comment->revisions(),'revisions() : %s');
	}

	public function testBadKittyMakesUpStuff() {
		$this->loadFixtures(
			'RevisionComment','RevisionCommentsRev',
			'RevisionCommentsRevisionTag','RevisionCommentsRevisionTagsRev',
			'RevisionTag','RevisionTagsRev'
		);

		$Comment = new RevisionComment();

		$Comment->id = 1;
		$this->assertFalse($Comment->revertTo(10),'revertTo() : %s');
		$this->assertFalse($Comment->diff(1,4),'diff() between existing and non-existing : %s');
		$this->assertFalse($Comment->diff(10,4),'diff() between two non existing : %s');
	}

	public function testMethodsOnNonRevisedModel() {
		$User = new RevisionUser();

		$User->id = 1;
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->createRevision());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($User->diff());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->initializeRevisions());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($User->newest());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertNull($User->oldest());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->previous());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->revertAll(array('date'=>'1970-01-01')));
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->revertTo(2));
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertTrue($User->revertToDate('1970-01-01'));
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->revisions());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->undo());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->undelete());
		$this->expectException('PHPUnit_Framework_Error');
		$this->assertFalse($User->updateRevisions());
	}

	public function testRevisions() {
		$this->loadFixtures('RevisionPost','RevisionPostsRev');

		$Post = new RevisionPost();

		$Post->create(array('Post' => array(
			'title' => 'Stuff (1)',
			'content' => 'abc'
		)));
		$Post->save();
		$postID = $Post->id;

		$Post->data = null;
		$Post->id = null;
		$Post->save(array('Post' => array(
			'id' => $postID,
			'title' => 'Things (2)'
		)));

		$Post->data = null;
		$Post->id = null;
		$Post->save(array('Post' => array(
			'id' => $postID,
			'title' => 'Machines (3)'
		)));


		$Post->bindModel(array(
			'hasMany' => array(
				'Revision' => array(
						'className' => 'RevisionPostsRev',
						'foreignKey' => 'id',
						'order' => 'version_id DESC'
				)
			)
		));
		$result = $Post->read(null,$postID);
		$this->assertEqual('Machines (3)',$result['Post']['title']);
		$this->assertIdentical(3,sizeof($result['Revision']));
		$this->assertEqual('Machines (3)',$result['Revision'][0]['title']);
		$this->assertEqual('Things (2)',$result['Revision'][1]['title']);
		$this->assertEqual('Stuff (1)',$result['Revision'][2]['title']);

		$result = $Post->revisions();
		$this->assertIdentical(2,sizeof($result));
		$this->assertEqual('Things (2)',$result[0]['Post']['title']);
		$this->assertEqual('Stuff (1)',$result[1]['Post']['title']);

		$result = $Post->revisions(array(), true);
		$this->assertIdentical(3,sizeof($result));
		$this->assertEqual('Machines (3)',$result[0]['Post']['title']);
		$this->assertEqual('Things (2)',$result[1]['Post']['title']);
		$this->assertEqual('Stuff (1)',$result[2]['Post']['title']);
	}
}
?>
