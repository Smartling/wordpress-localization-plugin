<?php 






class Smartling_Connector_XML {
	
	//public $xml;

	// public function __construct()
	// {
	// 	$this->xml = new
	// }
	public function getPost($id)
	{
		$post = is_int($id) ? get_post($id) : $id;

		return $post;
	}


	public function xmlTemplate($post)
	{
		$template = <<<XML
			<post>
				<authorID>$post->post_author</authorID>
				<date>$post->post_date</date>
				<title>$post->post_title</title>
				<content>$post->post_content</content>
				<status>$post->post_status</status>
			</post>
XML;

		return $template;
	}


	public function createXML($post)
	{
		$post     = $this->getPost($post);
		$template = $this->xmlTemplate($post);
		$xml      = new SimpleXMLElement($template);
		#$xml->asXML('example.xml');
	}
}