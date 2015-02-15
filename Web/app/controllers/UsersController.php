<?php

namespace APP\CONTROLLERS;

use APP\HELPERS\Upload;
use App\Models\User as User;

/**
 * Class IndexController
 */
class UsersController extends BaseController
{

    /*
     * GET users/subscribe
     */
    public function subscribe()
    {
        $this->render(true);
    }


    /**
     * POST users/create
     * @throws \APP\HELPERS\Exception
     */
    function create()
    {
        $validForm = User::checkFormSubscribe($this->f3->get('POST'));

        if ($validForm === true) {
            $username = User::where('username', $this->f3->get('POST.username'))->first();
            $mail = User::where('mail', $this->f3->get('POST.mail'))->first();

            if ($username === null && $mail === null) {

                if ($this->f3->get('FILES.avatar')) {
                    $upload = new Upload();
                    $path = $upload->save($this->f3->get('FILES.avatar'));
                } else {
                    $path = null;
                }


                // ADD A SPECIFIC CASE FOR USER CREATE FROM ADMIN


                $user = User::create(array(
                    'username'      => $this->f3->get('POST.username'),
                    'firstname'     => $this->f3->get('POST.firstname'),
                    'name'          => $this->f3->get('POST.lastname'),
                    'mail'          => $this->f3->get('POST.mail'),
                    'date_of_birth' => $this->f3->get('POST.birthday'),
                    'password'      => $this->crypt($this->f3->get('POST.password_1')),
                    'avatar_url'    => $path
                ));



                $this->f3->set('POST.id', $user->id);
                $this->f3->set('SESSION.user', $this->f3->get('POST'));

                $this->f3->reroute('/users/login', true); // TODO change it to the Dashboard
            } else {
                $validForm = [];
                if ($username !== null) {
                    array_push($validForm, 'The username is already token');
                }
                if ($mail !== null) {
                    array_push($validForm, 'The mail is already token');
                }
            }
        }

        $this->render('users/subscribe.twig', [
            'messages' => $validForm,
            'values'   => $this->f3->get('POST')
        ]);

    }

    /*
     * GET users/login
     */
    public function login()
    {
        $this->render(true, [
            'values'   => ($this->f3->get('SESSION.user.username')) ? $this->f3->get('SESSION.user.username') : '',
            'messages' => ($this->fMessage->get()) ? ($this->fMessage->get()) : ''
        ]);
    }

    /*
     * POST users/connect
     */
    public function connect()
    {

        $validForm = User::checkFormConnect($this->f3->get('POST'));

        if ($validForm === true) {
            $user = User::where('username', $this->f3->get('POST.username'))
                ->where('password', $this->crypt($this->f3->get('POST.password')))
                ->first();

            $validForm = [];

            if ($user !== null) {
                $this->f3->set('SESSION.user', $user);
                $this->fMessage->set('You are successfully logged');
                $this->f3->reroute('/dashboard', true);
            } else {
                array_push($validForm, "User don't exist");
            }
        }

        $this->render('users/login.twig', [
            'messages' => $validForm,
            'values'   => $this->f3->get('POST')
        ]);
    }

    /*
     * GET users/lostpassword
     */
    public function lostPassword()
    {
        // TODO Do stuff
    }

    /*
     * POST users/lostpassword
     */
    public function seedMailLostPassword()
    {
        // TODO Do stuff
    }

    /*
     * GET users/lostpassword/@username/@token
     */
    public function confirmLostPassword()
    {
        // TODO Do stuff
    }

}