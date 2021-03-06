<?php

namespace MyDropper\controllers;

use MyDropper\helpers\Mail;
use MyDropper\helpers\Url ;
use MyDropper\models\Category;
use MyDropper\models\Role;
use MyDropper\models\Store;
use MyDropper\models\User;
use MyDropper\models\Url as UrlModel;
use MyDropper\models\TrackerStore;
use MyDropper\models\TrackerUrl;

/**
 * Class UsersController
 * @package MyDropper\Controllers
 */
class UsersController extends BaseController
{

    /**
     * GET users/subscribe
     */
    public function subscribe()
    {
        $this->need->unLogged('/history')->execute();

        $this->render(true);
    }


    /**
     * POST users/create
     */
    public function create()
    {
        $validForm = User::checkFormSubscribe($this->f3->get('POST'));

        if ($validForm === true) {
            $username   = User::where('username', $this->f3->get('POST.username'))->first();
            $mail       = User::where('mail', $this->f3->get('POST.mail'))->first();


            if ($username === null && $mail === null) {

                // Create the User
                $user = User::create(array(
                    'username'        => $this->f3->get('POST.username'),
                    'firstname'       => $this->f3->get('POST.firstname'),
                    'name'            => $this->f3->get('POST.lastname'),
                    'mail'            => $this->f3->get('POST.mail'),
                    'password'        => $this->crypt($this->f3->get('POST.password_1')),
                    'mail_pushbullet' => $this->f3->get('POST.pushbullet'),
                    'avatar_url'      => 'assets/images/default-avatar.jpg',
                    'token_api'       => uniqid("API_")
                ));

                // Create default Category with Store
                $category = Category::create([
                    'user_id' => $user->id,
                    'label'   => 'Personnal Info'
                ]);

                Store::create([
                    'user_id'     => $user->id,
                    'category_id' => $category->id,
                    'label'       => 'Email',
                    'descript'    => $user->mail
                ]);

                // Save the user in Session
                $this->f3->set('POST.id', $user->id);
                $this->f3->set('SESSION.user', $user);

                // Redirect the user
                $this->f3->reroute(($user->has_extension == 1) ? '/history' : '/chrome-extension', true);
            } else {
                $validForm = [];
                if ($username !== null) {
                    $validForm[] = "The username is already token.";
                }
                if ($mail !== null) {
                    $validForm[] = "The mail is already token.";
                }
            }
        }

        $this->render('users/subscribe.twig', [
            'messages' => $validForm,
            'values'   => $this->f3->get('POST')
        ]);
    }

    /**
     *
     */
    public function delete()
    {
        $user = $this->need->logged('/users/login')->user()->execute();

        $remove = new Removal($user->id, 'User');
        $remove->cascade(['Category', 'Store', 'TrackerStore'], false);
        User::destroy($user->id);

        $this->f3->clear('SESSION');
        $this->fMessage->set('Your account is deleted', 'alert');
        $this->f3->reroute('/users/login');
    }

    /**
     * GET users/login
     */
    public function login()
    {
        $this->need->unLogged('/history')->execute();

        $this->render(true, [
            'values' => ($this->f3->get('SESSION.user.username')) ? $this->f3->get('SESSION.user.username') : ''
        ]);
    }

    /**
     * POST users/connect
     */
    public function connect()
    {
        $validForm = User::checkForm($this->f3->get('POST'), array(
            'username' => 'required',
            'password' => 'required'
        ));

        if ($validForm === true) {
            $user = User::where('username', $this->f3->get('POST.username'))->where('password',
                $this->crypt($this->f3->get('POST.password')))->first();
            $validForm = [];

            if ($user !== null) {
                $this->f3->set('SESSION.user', $user);
                $this->fMessage->set('You are successfully logged');
                $this->f3->reroute(($user->has_extension == 1) ? '/history' : '/chrome-extension', true);
            } else {
                $validForm[] = "User doesn't exist";
            }
        }

        $this->render('users/login.twig', [
            'messages' => $validForm,
            'values'   => $this->f3->get('POST')
        ]);
    }

    /**
     * GET users/lostpassword
     */
    public function lostPassword()
    {
        $this->need->unLogged('/history')->execute();

        $this->render(true);
    }

    /**
     * POST users/lostpassword
     */
    public function seedMailLostPassword()
    {
        $validForm = User::checkForm($this->f3->get('POST'), array(
            'mail' => 'required|valid_email'
        ));

        if ($validForm === true) {
            $userInformations = User::where('mail', $this->f3->get('POST.mail'))->first();
            $token = uniqid();
            $validForm = [];

            if ($userInformations !== null) {
                // Generate Token and save it
                $user = User::find($userInformations->id);
                $user->token_password = $token;
                $user->is_lost_password = 1;
                $user->save();

                // Define URL
                $urlHelper = new Url();
                $url = $urlHelper->generate('/users/lostpassword/', array(
                    $userInformations->username,
                    $token
                ));

                // Send Mail
                $mail = new Mail();
                $mail->send('lostpassword_first_step', $this->f3->get('POST.mail'), array(
                    'subject' => 'Mot de passe oublié',
                    'link'    => $url
                ));

                // Display Messages
                if ($mail) {
                    $validForm[] = "Message seeded.";
                } else {
                    $validForm[] = "Error during seeding mail. Try again.";
                }
            } else {
                $validForm[] = "The email does not exist in our database.";
            }
        }

        $this->render('users/lostpassword.twig', [
            'messages' => $validForm
        ]);
    }

    /**
     * GET users/lostpassword/@username/@token
     */
    public function confirmLostPassword()
    {
        $userInformations = User::where('username', $this->f3->get('PARAMS.username'))->where('token_password',
            $this->f3->get('PARAMS.token'))->first();
        $messages = [];

        if ($userInformations !== null) {
            $newPassword = uniqid();

            // Save new Password
            $user = User::find($userInformations->id);
            $user->token_password = null;
            $user->is_lost_password = 0;
            $user->password = $this->crypt($newPassword);
            $user->save();

            // Send a mail with new Password
            $mail = new Mail();
            $mail->send('lostpassword_final_step', $userInformations->mail, array(
                'subject'  => 'Mot de passe oublié',
                'password' => $newPassword
            ));

            $messages[] = "Your new password has been sent to your mail.";

            // After 3s, redirect to the login page
            sleep(3);
            $this->f3->reroute('/users/login', true);
        } else {
            $messages[] = "The token does not match with the username";
        }

        $this->render('users/lostpassword.twig', [
            'messages' => $messages
        ]);
    }

    /**
     * Logout the user
     */
    public function logout()
    {
        $this->f3->clear('SESSION');
        $this->fMessage->set('You are successfully logout', 'alert');
        $this->f3->reroute('/');
    }

    /**
     * List all users
     * GET /admin/users
     */
//    TODO Check if usefull at the end
    public function admin_index()
    {
        $this->need->logged('/users/login')->minimumLevel(9)->user()->execute();

        $users = User::with('roles')->get();
        $usersCount = count($users);

        $this->render(true, [
            'users'      => $users,
            'usersCount' => $usersCount
        ]);
    }

    /**
     *GET|POST /admin/users/edit/@id
     */
    public function admin_edit()
    {
        $this->need->logged('/users/login')->minimumLevel(9)->user()->execute();

        $id = (int)($this->f3->get('PARAMS.id'));
        $validForm = null;

        if ($this->f3->get('POST') && $id > 0) {
            $validForm = User::checkAdminEdit($this->f3->get('POST'), $id);
            if ($validForm === true) {
                User::where('id', '=', $id)->update([
                    'username'  => $this->f3->get('POST.username'),
                    'firstname' => $this->f3->get('POST.firstname'),
                    'name'      => $this->f3->get('POST.name'),
                    'mail'      => $this->f3->get('POST.mail'),
                    'role_id'   => $this->f3->get('POST.role_id')
                ]);
            }
        }
        $user = User::with('roles')->find($id);

        $storesCount = Store::where('user_id', '=', $id)->count();
        $storesCountAll = Store::where('user_id', '=', $id)->withTrashed()->count();

        $categoriesCount = Category::where('user_id', '=', $id)->count();
        $categoriesCountAll = Category::where('user_id', '=', $id)->withTrashed()->count();

        $urlsCount = UrlModel::where('user_id', '=', $id)->count();
        $urlsCountAll = UrlModel::where('user_id', '=', $id)->withTrashed()->count();

        $trackersStoresCount = TrackerStore::where('user_id', '=', $id)->count();
        $trackersStoresCountAll = TrackerStore::where('user_id', '=', $id)->withTrashed()->count();

        $trackersUrlsCount = TrackerUrl::where('user_id', '=', $id)->count();
        $trackersUrlsCountAll = TrackerUrl::where('user_id', '=', $id)->withTrashed()->count();


        $roles = Role::all();

        $this->render(true, [
            'messages'   => $validForm,
            'values'     => $user,
            'roles'      => $roles,
            'storesCount' => $storesCount,
            'storesCountAll' => $storesCountAll,
            'categoriesCount' => $categoriesCount,
            'categoriesCountAll' => $categoriesCountAll,
            'urlsCount' => $urlsCount,
            'urlsCountAll' => $urlsCountAll,
            'trackersStoresCount' => $trackersStoresCount,
            'trackersStoresCountAll' => $trackersStoresCountAll,
            'trackersUrlsCount' => $trackersUrlsCount,
            'trackersUrlsCountAll' => $trackersUrlsCountAll,
        ]);
    }

    /**
     * Delete a user
     * GET /admin/users/delete/@id
     */
    public function admin_delete()
    {
        $this->need->logged('/users/login')->minimumLevel(9)->user()->execute();
        $userId = (int)($this->f3->get('PARAMS.id'));

        if ($userId) {
            $user = User::find($userId);

            // Send Mail
            $mail = new Mail();
            $mail->send('default', $user->mail, array(
                'subject'     => 'Deleted account',
                'content' => "Your account has been delete by an administrator of the Mydropper"
            ));
            $user->delete();

            $this->fMessage->set('The account is deleted', 'alert');
            $this->f3->reroute('/admin/users');
        } else {
            $this->f3->reroute('/admin/users');
        }
    }
}
