<?php

use phpDocumentor\Reflection\DocBlock\Tags\Reference\Url;

defined('BASEPATH') OR exit('No direct script access allowed');
require_once "application/models/MainModel.php";
require_once "application/models/CallbacksValidation.php";
require_once "application/models/Mail.php";
require_once "application/models/Strings.php";

class Main extends CI_Controller implements Strings{
use MainModel;
use CallbacksValidation;
    public function __construct(){
                parent::__construct();
                $this->load->helper(array('url'));
                $this->load->library('session');
                $this->content = $this->set_button();
    }

    function file_not_found(){
        $this->load->view("404.php");
    }
    function index(){
        $this->load->view('index',$this->content);
    }
    
    function submit_game(){
        if (!$this->is_online())
            $this->redirect_msg(Strings::LOGIN_REQUIRED,'signup');

        $this->load->helper("form"); 
        $this->load->view('submit_game',$this->content);
    }   
    function things_to_know(){
        $this->load->view('things_to_know',$this->content);
    } 
    function logout(){
        session_destroy();  
        redirect("");     
    }
    function upload_thumbnail(){
        if ($this->input->get_request_header('X-CSRF-Token', TRUE) == $this->security->get_csrf_hash()  && $this->is_online()){
            $this->load->library('SQNile');
            $filename = $this->sqnile->fetch("SELECT thumbnail from games WHERE `dev_id` = ?",[$this->session->dev["dev_id"]]);
            $filename != null ? $filename = intval($filename['thumbnail'])+2 : $filename = 2 ;
            $config['upload_path']  = './uploads/'.$this->session->dev["dev_id"]."/";
            $config['allowed_types']  = 'jpeg|jpg|png';
            $config['file_name']  = $filename.'.png';
            $config['overwrite'] = true;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('filepond'))
                $this->session->thumbnail = $filename;
            else
                throw new Exception(Strings::UNKNOWN_ERROR);
        }else
            throw new Exception(Strings::UNKNOWN_ERROR);
    }
    function upload_apk(){
        if ($this->input->get_request_header('X-CSRF-Token', TRUE) == $this->security->get_csrf_hash() && $this->is_online()){
            $this->load->library("SQNile");
            $filename = $this->sqnile->fetch("SELECT apk from games WHERE `dev_id` = ?",[$this->session->dev["dev_id"]]);
            $filename != null ?  $filename =intval($filename['apk']) + 2 :  $filename = 1 ;
            $config['upload_path']  = './uploads/'.$this->session->dev["dev_id"]."/";
            $config['allowed_types']  = 'apk';
            $config['file_name']  =  $filename.'.apk';
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('filepond'))
                $this->session->apk = $filename;
            else
                throw new Exception(Strings::UNKNOWN_ERROR);
        }else
            throw new Exception(Strings::UNKNOWN_ERROR);
    }
    function upload_game(){
        if (!$this->is_online())
            $this->redirect_msg(Strings::LOGIN_REQUIRED,'signup');

        $this->load->helper("form");
        $this->load->library("form_validation");

        $this->form_validation->set_rules('title', 'Title', 'trim|required|min_length[3]|max_length[12]|callback_alpha_dash_space');
        $this->form_validation->set_rules('price', 'Price', 'trim|required|is_natural_no_zero');
        $this->form_validation->set_rules('message', 'Desicription', 'trim|required|callback_alpha_dash_space|min_length[20]|max_length[1024]');
        if ($this->form_validation->run() != FALSE){
            $this->load->library("SQNile");
            try{
                $this->sqnile->query("INSERT INTO games (dev_id,apk,thumbnail,price,title,description,time) VALUES (?,?,?,?,?,?,?)",
                [   $this->session->dev["dev_id"],
                    $this->session->apk,    
                    $this->session->thumbnail,
                    $this->input->post("price",true),
                    $this->input->post("title",true),
                    $this->input->post("message",true),
                    time()
                ]);
                echo Strings::GAME_UPLOADED; 
            }catch(Exception $e){ 
                if ($e->getCode() === 1001) echo Strings::UPLOAD_REQUIRED;
                else echo Strings::UNKNOWN_ERROR;    
            }
        }else
            echo validation_errors();
    }
    function dashboard(){
        if (!$this->is_online())
            $this->redirect_msg(Strings::LOGIN_REQUIRED,'signup');

        $this->load->helper("form");
        $this->load->library('SQNile');
        $payout = $this->sqnile->fetch("SELECT income,payout FROM developer WHERE dev_id = ?",[$this->session->dev['dev_id']]);
        $income = 0;
        $payout['income'] -= $payout['payout'];
        $content = array_merge($this->session->dev,array('income'=> $income));
        $this->session->payout = $income;
        $this->load->view('user_setting',$content);
    }
    function payout(){
        if (!$this->is_online())
            $this->redirect_msg(Strings::LOGIN_REQUIRED,'signup');
        $this->load->model('Mail');
        $error = $this->mail->send_mail(
            'pratikmazumdar680@protonmail.com',
            $this->session->dev['dev_id'].' payout : '.$this->session->payout
        );
        $this->sqnile->query("INSERT INTO developer (payout) VALUES (?)",$this->session->payout);
        $error == null ? $this->session->msg = String::PAYOUT_DONE : $this->session->msg = Strings::UNKNOWN_ERROR;
    }
    function update_dev(){
        if (!$this->is_online())
            $this->redirect_msg(Strings::LOGIN_REQUIRED,'signup');

        $this->load->library("form_validation");
        $this->load->library("SQNile");
        $this->load->helper("form");
        
        $this->form_validation->set_rules('company_name', 'Company Name', 'trim|required|callback_alpha_dash_space');
        $this->form_validation->set_rules('current_password', 'Current Password', 'required|min_length[8]|callback_verify_password');
        $this->form_validation->set_rules('new_password', 'New Password', 'min_length[8]|callback_match_confirm');
        $this->form_validation->set_rules('confim_password', 'Confirm Password', 'min_length[8]');
        $this->form_validation->set_rules('countries', 'Countries', 'trim|required|callback_alpha_dash_space');
        $this->form_validation->set_rules('ifsc', 'IFSC Code', 'trim|alpha_numeric');
        $this->form_validation->set_rules('ac_number', 'Account Number', 'trim|is_natural');
        $this->form_validation->set_rules('iban', 'IBAN', 'trim|alpha_numeric');
        $this->form_validation->set_rules('bic', 'BIC', 'trim|alpha_numeric');
        $this->form_validation->set_rules('short_code', 'Short Code', 'trim|is_natural');
        
        if ($this->form_validation->run()){
            $post = $this->input->post(NULL,true);
            // If user want to change their password
            if ($post['confirm_password'] != null && $post['new_password'] != null ){
                $post['new_password'] = password_hash($post['new_password'],PASSWORD_BCRYPT); 
                $this->sqnile->query("UPDATE developer SET `password` = ? WHERE dev_id = ?",
                [$post['new_password'],$this->session->dev['dev_id']]);
            }

            // If user want to update their bank account details
            if ($this->input->post('checkbox',true)){
                $this->sqnile->query("UPDATE account SET country=?,ifsc=?,ac_number=?,iban=?,bic=?,short_code=? WHERE dev_id = ?",
                    [
                        $post['countries'], $this->input->post('ifsc',true),
                        $this->input->post('ac_number',true),
                        $this->input->post('iban',true), $this->input->post('bic',true),
                        $this->input->post('short_code',true),$this->session->dev['dev_id'],
                    ]
                );
            }
            // If user want to change their company name
            if ($this->session->dev['comp_name'] != $post['company_name']){
                $this->sqnile->query("UPDATE developer SET `comp_name` = ? WHERE dev_id = ?",
                [$post['company_name'],$this->session->dev['dev_id']]);
                $_SESSION['comp_name'] = $post['company_name'];
            }
            
        }else{
            $error = $this->form_validation->error_array();
            $key = array_key_first($error);
            $this->redirect_msg($error[$key],'dashboard');
        }
    }
}
