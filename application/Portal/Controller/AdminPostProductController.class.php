<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Tuolaji <479923197@qq.com>
// +----------------------------------------------------------------------
namespace Portal\Controller;

use Portal\Controller\AdminPostController;
use Think\Db;

class AdminPostProductController extends AdminPostController{

    protected $product_detail_model;

    function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->product_detail_model = M("ProductDetail");
    }

    // 文章添加
    public function add(){
        $terms = $this->terms_model->order(array("listorder"=>"asc"))->select();
        $term_id = I("get.term",0,'intval');
// 		$this->_getTermTree();
        $this->getTree();
        $term=$this->terms_model->where(array('term_id'=>$term_id))->find();
        $this->assign("term",$term);
        $this->assign("terms",$terms);
        $this->display();
    }

    // 文章添加提交
    public function add_post(){
        if (IS_POST) {
            if(empty($_POST['term'])){
                $this->error("请至少选择一个分类！");
            }
            if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
                foreach ($_POST['photos_url'] as $key=>$url){
                    $photourl=sp_asset_relative_url($url);
                    $_POST['smeta']['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
                }
            }
            $_POST['smeta']['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']);

            $_POST['post']['post_modified']=date("Y-m-d H:i:s",time());
            $_POST['post']['post_author']=get_current_admin_id();
            $article=I("post.post");
            $article['smeta']=json_encode($_POST['smeta']);
//            $article['post_content']=htmlspecialchars_decode($article['post_content']);
            $result=$this->posts_model->add($article);
            if ($result) {
                foreach ($_POST['term'] as $mterm_id){
                    $this->term_relationships_model->add(array("term_id"=>intval($mterm_id),"object_id"=>$result));
                }

                // 插入详情
                $detail = I("post.detail");
                $detail['object_id'] = $result;
                $detail['parameter'] = htmlspecialchars_decode($detail['parameter']);
                $detail['intro'] = htmlspecialchars_decode($detail['intro']);
                $this->product_detail_model->add($detail);

                $this->success("添加成功！");
            } else {
                $this->error("添加失败！");
            }

        }
    }

    // 文章编辑
    public function edit(){
        $id=  I("get.id",0,'intval');

        $term_relationship = M('TermRelationships')->where(array("object_id"=>$id,"status"=>1))->getField("term_id",true);
        $this->_getTermTree($term_relationship);
        $terms=$this->terms_model->select();
        $post=$this->posts_model->where("id=$id")->find();

        if ($post) {
            $detail = M('ProductDetail')->where(array('object_id' => $id))->find();
            unset($detail['id']);
            unset($detail['object_id']);
            $post = is_array($detail) ? array_merge($post, $detail) : $post;
        }



        $this->assign("post",$post);
        $this->assign("smeta",json_decode($post['smeta'],true));
        $this->assign("terms",$terms);
        $this->assign("term",$term_relationship);
        $this->display();
    }

    // 文章编辑提交
    public function edit_post(){
        if (IS_POST) {
            if(empty($_POST['term'])){
                $this->error("请至少选择一个分类！");
            }
            $post_id=intval($_POST['post']['id']);

            $this->term_relationships_model->where(array("object_id"=>$post_id,"term_id"=>array("not in",implode(",", $_POST['term']))))->delete();
            foreach ($_POST['term'] as $mterm_id){
                $find_term_relationship=$this->term_relationships_model->where(array("object_id"=>$post_id,"term_id"=>$mterm_id))->count();
                if(empty($find_term_relationship)){
                    $this->term_relationships_model->add(array("term_id"=>intval($mterm_id),"object_id"=>$post_id));
                }else{
                    $this->term_relationships_model->where(array("object_id"=>$post_id,"term_id"=>$mterm_id))->save(array("status"=>1));
                }
            }

            if(!empty($_POST['photos_alt']) && !empty($_POST['photos_url'])){
                foreach ($_POST['photos_url'] as $key=>$url){
                    $photourl=sp_asset_relative_url($url);
                    $_POST['smeta']['photo'][]=array("url"=>$photourl,"alt"=>$_POST['photos_alt'][$key]);
                }
            }
            $_POST['smeta']['thumb'] = sp_asset_relative_url($_POST['smeta']['thumb']);
            unset($_POST['post']['post_author']);
            $_POST['post']['post_modified']=date("Y-m-d H:i:s",time());
            $article=I("post.post");
            $article['smeta']=json_encode($_POST['smeta']);
//            $article['post_content']=htmlspecialchars_decode($article['post_content']);

            $result=$this->posts_model->save($article);
            if ($result !== false) {
                // 编辑详情
                $detail = I("post.detail");
                $detail['parameter'] = htmlspecialchars_decode($detail['parameter']);
                $detail['intro'] = htmlspecialchars_decode($detail['intro']);
                if ($this->product_detail_model->where(array('object_id' => $post_id))->find()) {
                    $this->product_detail_model->where(array('object_id' => $post_id))->save($detail);
                } else {
                    // 如果没有则插入
                    $detail['object_id'] = $post_id;
                    $this->product_detail_model->add($detail);
                }
            }
            if ($result!==false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }
    }
	
}