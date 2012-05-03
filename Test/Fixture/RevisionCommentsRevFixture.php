<?php
class RevisionCommentsRevFixture extends CakeTestFixture {
	public $name = 'RevisionCommentsRev';
	public $fields = array(	
			'version_id' => array('type' => 'integer','null' => false,'default' => NULL,'key' => 'primary'), 
			'version_created' => array('type' => 'datetime', 'null' => false, 'default' => NULL),
			'id' => array(
					'type' => 'integer', 
					'null' => false, 
					'default' => NULL), 
			'title' => array('type' => 'string', 'null' => false, 'default' => NULL), 
			'content' => array('type' => 'text', 'null' => false, 'default' => NULL),
			'Tag' => array('type' => 'text', 'null' => true, 'default' => NULL)
	);
	public $records = array(	
		array(
			'version_id' => 1,
			'version_created' => '2008-12-08 11:38:53',
			'id' => 1, 
			'title' => 'Comment 1', 
			'content' => 'Lorem ipsum dolor sit amet, aliquet feugiat.', 
			'Tag' => '[{"id":"1","title":"Fun","RevisionCommentsRevisionTag":{"id":"1","revision_comment_id":"1","revision_tag_id":"1"}},{"id":"2","title":"Hard","RevisionCommentsRevisionTag":{"id":"2","revision_comment_id":"1","revision_tag_id":"2"}},{"id":"3","title":"Trick","RevisionCommentsRevisionTag":{"id":"3","revision_comment_id":"1","revision_tag_id":"3"}}]'
		),	
		array(
			'version_id' => 2,
			'version_created' => '2008-12-08 11:38:54',
			'id' => 2, 
			'title' => 'Comment 2', 
			'content' => 'Lorem ipsum dolor sit.', 
			'Tag' => '[{"id":"1","title":"Fun","RevisionCommentsRevisionTag":{"id":"4","revision_comment_id":"2","revision_tag_id":"1"}},{"id":"3","title":"Trick","RevisionCommentsRevisionTag":{"id":"5","revision_comment_id":"2","revision_tag_id":"3"}}]'
		),
		array(
			'version_id' => 3,
			'version_created' => '2008-12-08 11:38:55',
			'id' => 3, 
			'title' => 'Comment 3', 
			'content' => 'Lorem ipsum dolor sit.', 
			'Tag' => null
		),
	);
}
?>