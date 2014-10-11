<?php

defined('IN_APP') ? NULL : exit();

class Model_post extends Model {
    
    protected $_table = 'posts';
    
    protected $_key = 'id';
    
    protected $_properties = array(
        'id',
        'title',
        'type' => 'page',
        'content',
        'foo',
        'foo1'
    );
    
    protected function validate() {
        if(!$this->foo) return false;
        return true;
    }
    
}

class Task_crud extends Controller {
    
    public function _execute() {
        
        $post = Model::getInstance('post');
        var_dump($post);
        $post->title = 'some title';
        $post->type = 'product';
        $post->foo = 'footest';
        var_dump($post);
        $post->save();
        var_dump($post);
        
        return;
       
        new Model;
        
        // Create a post
        $post = new Model_posts;
        $post->type = 'page';
        $post->title = 'The title';
        $post->content = 'The content';
        $post->save();
        
        // Update a post after creation
        $post->type = 'product';
        $post->save();
        
        // Update a post by setting the id
        $post2 = new Model_posts;
        $post2->id = 'IyTZ0Wslcc';
        $post2->type = 'product2';
        $post2->save();
        
        return;
        
        
        // Load a post
        $post = new Model_posts;
        $post->id = '34';
        $post->load();
        //$posts = self::get('posts', 'data');
        
    }
    
    public function _post() {
        
        $postId =     self::set('post', array('title' => 'Some Title', 'content' => 'Some Content'), 'data');
        // Calls Model_demo::createPost($data)
        $updateTime = self::set('post.34', array('title' => 'Some New Title'), 'data');
        // Calls Model_demo::updatePost(34, $data)
        
    }
    
}

?>