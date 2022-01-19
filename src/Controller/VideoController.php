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
 
use Knp\Component\Pager\PaginatorInterface;

class VideoController extends AbstractController
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
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }
    
    public function create(Request $request, JwtAuth $jwt_auth, $id = null){
        
        //Default resp
        $data = [
          'status' => 'error',
          'code' => 400,
          'message' => 'Video registry error'
        ];
        
        //get token
        $token = $request->headers->get('Authorization',null);
        
        //Check token
        $authCheck = $jwt_auth->checkToken($token);
        
        if ($authCheck) {
            //get data from POST
            $json = $request->get('json',null);
            $params = json_decode($json);
        
            //get obj user identified
            $identity = $jwt_auth->checkToken($token,true);
            
            //Validate data
            if (!empty($json)) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;
                
                if (!empty($user_id) && !empty($title)) {
                    
                    //Save new video
                    
                    //Get user
                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                       'id'=>$user_id 
                    ]);
                    
                    
                    if ($id == null) { //Insert video
                        //Create obj video
                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');

                        $createdAt = new \DateTime('now');
                        $updatedAt = new \DateTime('now');

                        $video->setCreatedAt($createdAt);
                        $video->setUpdatedAt($updatedAt);

                        //Save
                        $em->persist($video);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Video registred successfully',
                            'video' => $video
                          ];
                    }else{ //Update video
                        
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id'=>$id,
                            'user'=> $identity->sub
                        ]);
                        
                        if ($video && is_object($video)) {
                            
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                           
                            $updatedAt = new \DateTime('now');
                            $video->setUpdatedAt($updatedAt);
                            
                            $em->persist($video);
                            $em->flush();   
                            
                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Video updated successfully',
                                'video' => $video
                              ];
                        }
                    }
                    
                }
            }
            
        }
                
        //Return response
        return $this->responseJson($data);
        
    }
    
    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator){
        
        //Get header auth
        $token = $request->headers->get('Authorization');
        
        //Check token
        $authCheck = $jwt_auth->checkToken($token);
        
        //If token is valid:
        if ($authCheck) {
            //Get user identity (User obj)
            $identity = $jwt_auth->checkToken($token,true);
         
            $em = $this->getDoctrine()->getManager();

            //Query to paginate
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $em->createQuery($dql);

            //Get param page from url
            $page = $request->query->getInt('page',1);
            $items_per_page = 6;

            //pagination
            $pagination = $paginator->paginate($query,$page,$items_per_page);
            $total = $pagination->getTotalItemCount();
            
            //set array data 
            $data = array(
                'status' => 'success',
                'code' => 200,
                'total_item_count' => $total,
                'page_actual'=>$page,
                'items_per_page'=>$items_per_page,
                'total_pages'=> ceil($total/$items_per_page),
                'videos'=>$pagination,
                'user_id'=>$identity->sub
            );
        }       
        else{
            //If fails return data fail        
            $data = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Videos not founded'
            );
        }
        
        return $this->responseJson($data);
    }
    
    public function detail(Request $request, JwtAuth $jwt_auth, $id = null){
        
        //Get token
        $token = $request->headers->get('Authorization');
        
        //Check token
        $authCheck = $jwt_auth->checkToken($token);
        
        //Default response
        $data = [
                'status' => 'error',
                'code' => 404,
                'message' => 'Video not found'
              ];
        
        if ($authCheck) {
            
            //Get user identity
            $identity = $jwt_auth->checkToken($token,true);
        
            //Get obj video from id url
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);

            //Check if video exists and user identoofied is owner
            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $data = [
                'status' => 'success',
                'code' => 200,
                'video' => $video
              ];
            }

        }
        
        //Return response
        return $this->responseJson($data);
    }
    
    public function remove(Request $request, JwtAuth $jwt_auth,$id = null){
        //Get token
        $token = $request->headers->get('Authorization');
        
        //Check token
        $authCheck = $jwt_auth->checkToken($token);
        
        //Default response
        $data = [
                'status' => 'error',
                'code' => 404,
                'message' => 'Video not found'
              ];
        
        if ($authCheck) {
            $identity = $jwt_auth->checkToken($token,true);
            
            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            
            $video = $doctrine->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
            
            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                
                $em->remove($video);
                $em->flush();
                
                $data = [
                'status' => 'success',
                'code' => 200,
                'video' => $video
              ];
            }
        }
        
        //Return response
        return $this->responseJson($data);
    }
}
