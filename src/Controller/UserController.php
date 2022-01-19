<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class UserController extends AbstractController
{
    private function responseJson($data){
        //Serialize data with service serializer
        $json = $this->get('serializer')->serialize($data,'json');
        
        //Response with httpfoundation
        $response = new Response();
        
        //Set content to response
        $response->setContent($json);
        
        //Set format response
        $response->headers->set('Content-Type','application/json');
        
        //Return response        
        return $response;        
    }
    
    public function index(): Response
    {
        
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);
        
        $video = $video_repo->findAll();
        $user = $user_repo->find(1);
        
        $data = [
            'message' => 'Welcome to your new controller! (Edited by netbeans)',
            'path' => 'src/Controller/UserController.php',
        ];
        
        //Pruebas ORM
        /*
        $users = $user_repo->findAll();
        foreach($users as $user){
            echo "<h1>".$user->getName()." ".$user->getSurName()."</h1>";
            echo "<br>";
            foreach($user->getVideos() as $video){
            echo "<p>".$video->getTitle()."-".$video->getUser()->getEmail()."</p>";
            }
            
        }
        die();*/
        
        return $this->responseJson([
            $user
        ]);
    }
    
    public function create(Request $request){
        
        //Get params (From post)
        $json = $request->get('json',null);

        //Decode JSON
        $params = json_decode($json);
        
        //Response default
        $data = [
          'status' => 'error',
          'code' =>  200,
          'message' => 'User not registed'
        ];
        
        //Check data
        if ($json != null) {
            
            //Validate info from params
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
                   
            //Validate email
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email,[
               new Email() 
            ]);
            
            
            if(!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)){
                
                //If check true, create obj
                $user = new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));                
        
                //Create password
                $pwd = hash('sha256',$password);
                $user->setPassword($pwd);
                
                //$data = $user;
                //var_dump($user);
                //die();

                //Check user if already exists
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();
                
                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                   'email' => $email 
                ));
                
                //Condition
                if(count($isset_user) == 0){
                    //save user
                    $em->persist($user);
                    $em->flush();
                    
                    $data = [
                        'status' => 'success',
                        'code' =>  200,
                        'message' => 'User created succesfully',
                        'user' => $user
                    ];
                    
                }else{
                    $data = [
                        'status' => 'error',
                        'code' =>  400,
                        'message' => 'User already exists'
                    ];
                }
                
            }
        }
                
        //Return response
        //return $this->responseJson($data);
        return new JsonResponse($data);
    }
    
    public function login(Request $request, JwtAuth $jwt_auth){
        //get data from post
        $json = $request->get('json',null);
        
        //Decode JSON
        $params = json_decode($json);
        
        //Default array
        $data = [
                'status' => 'error',
                'code' =>  200,
                'message' => 'User not valid'
        ];
        
        //Check and validate data
        if ($params != null) {
            
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;
            
            //Validate email
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email,[
               new Email() 
            ]);
            
            if (!empty($email) && count($validate_email) == 0 && !empty($password)) {
                
                //ccyph password
                $pwd = hash('sha256',$password);
                
                //if valid == true, call a service to identify user
                if ($gettoken) {
                    $signup = $jwt_auth->signup($email, $pwd, $gettoken);
                }else{
                    $signup = $jwt_auth->signup($email, $pwd);
                }
                
                return new JsonResponse($signup);
            }
        }
        
        //Si return is correct set new return
        return $this->responseJson($data);
    }
    
    public function edit(Request $request, JwtAuth $jwt_auth){
        
        //Get header auth
        $token = $request->headers->get('Authorization');
        
        //create method check token correct
        $authCheck = $jwt_auth->checkToken($token);
        
        //Default data
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'User not founded'
        ];
        
        //Update user info if token is correct
        if ($authCheck) {
            //update user
            
            //Get identity manager
            $em = $this->getDoctrine()->getManager();
            
            //Get data user authenticated
            $identity =  $jwt_auth->checkToken($token,true);
            //var_dump($identity);die();
            
            //Get user update (Query db)
            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id'=>$identity->sub
            ]);
            
            //Get data from post
            $json = $request->get('json',null);
            $params = json_decode($json);
            
            //Validate data
            if (!empty($json)) {
                //Validate info from params
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;

                //Validate email
                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email,[
                   new Email() 
                ]);


                if(!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)){
                    
                    //Set new data
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);
            
                    //Check if user already exists
                    $isset_user = $user_repo->findBy([
                       'email' => $email 
                    ]);
                    
                    if (count($isset_user) == 0 || $identity->email === $email) {
                        //Save changes
                        $em->persist($user);
                        $em->flush();
                        
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'User updated',
                            'user' => $user
                        ];
                     
                    }else{
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'User not valid'
                        ];
                    }
                }
                
            }           
            
        }
                
        return $this->responseJson($data);
    }
}
