<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth{
    
    public $manager;
    public $key;
    
    public function __construct($manager) {
        $this->manager = $manager;
        $this->key = 'hola_que_tal_este_es_el master14314324';
    }
    
    public function signup($email,$password, $gettoken=null){
        
        //Check if user exists
        $user = $this->manager->getRepository(User::class)->findOneBy([
           'email' => $email,
            'password' => $password
        ]);
        
        $signup = false;
        
        if (is_object($user)) {
            $signup = true;
        }
        
        //var_dump($signup);
        //die();
        
        //Generate token
        if ($signup){
            
            $token = [
              'sub' => $user->getId(),
              'name' => $user->getName(),
              'surname' => $user->getSurname(),
              'email' => $user->getEmail(),
              'lat' => time(),
              'exp' => time() + (7*24*60*60) 
            ];
            
            $jwt = JWT::encode($token, $this->key, 'HS256');
            
            //Check flag gettoken     
            if (!empty($gettoken)) {                
                $data = $jwt;
            }else{
                $decoded = JWT::decode($jwt, $this->key, ['HS256']);
                $data = $decoded;
            }
            
        } else{
            //If user or password are incorrect
            $data = [
              'status' => 'error',
                'message' => 'User or password are incorrect'
            ];
        }      
        
        //Return data
        return $data;
    }
    
    public function checkToken($jwt,$identity=false){
        $auth = false;
        
        try {
            $decoded = JWT::decode($jwt, $this->key, ['HS256']);

        } catch (\UnexpectedValueException $ex) {
            $auth = false;
        }  
        catch (\DomainException $ex) {
            $auth = false;
        }
        
        if (isset($decoded) && !empty($decoded) && is_object($decoded) && isset($decoded->sub)) {
            $auth = true;
        }else{
            $auth = false;
        }
        
        if ($identity != false) {
            return $decoded;
        }else{
            return $auth;
        }        
    }
}
