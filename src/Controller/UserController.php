<?php

namespace App\Controller;

use App\Model\UserManager;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends Controller
{
    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function indexAction()
    {
        return $this->render('register.twig');
    }

    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function registerAction()
    {
        $data['username'] = ucfirst(strtolower(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS)));
        $data['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $data['password'] = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        $data['password2'] = filter_input(INPUT_POST, 'passwordconfirm', FILTER_SANITIZE_STRING);
        $data['firstname'] = ucfirst(strtolower(filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_SPECIAL_CHARS)));
        $data['lastname'] = ucfirst(strtolower(filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_SPECIAL_CHARS)));

        // Ensure that the form is correctly filled
        if (count(array_filter($data)) === 6) {
            $userManager = new UserManager();
            $error = $this->verifyUser($data);
            // register user if there are no errors in the form
            if (!empty($error)) {

                return $this->render("register.twig", array("error" => $error, "data" => $data));
            }
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);//encrypt the password before saving in the database
            $userManager->createUser($data);
            $info = $userManager->getUser($data['email']);
            $status = $this->session->checkStatus($info['status']);
            $this->session->createSession($info['id'], $info['username'], $info['email'], $status);
            $this->alert("Votre compte a été créé avec succès !");

            return $this->render('home.twig', array('session' => filter_var_array($_SESSION)));
        }
        $this->alert("Veuillez remplir tous les champs !");

        Return $this->render("register.twig");
    }

    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function loginAction()
    {
        $email = filter_input(INPUT_POST, 'emaillog', FILTER_SANITIZE_SPECIAL_CHARS);
        $password = filter_input(INPUT_POST, 'passwordlog', FILTER_SANITIZE_STRING);

        if (!empty($email) and !empty($password)) {
            $userManager = new UserManager();
            $info = $userManager->checkUser($email);
            if ($info !== false) {
                $info = $userManager->getUser($email);
                if (password_verify($password, $info['password']) === true) {
                    $status = $this->session->checkStatus($info['status']);
                    $this->session->createSession($info['id'], $info['username'], $info['email'], $status);
                    $this->alert("Vous êtes maintenant connecté !");

                    return $this->render('home.twig', array('session' => filter_var_array($_SESSION)));
                }
            }
            $this->alert('Informations incorrectes, veuillez réessayer !');

            return $this->render("home.twig");
        }
        $this->alert("Veuillez remplir tous les champs !");

        return $this->render("home.twig");
    }

    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function logoutAction()
    {
        if ($this->session->isLogged()) {
            $this->session->destroySession();
        }
        return $this->render('home.twig', array('session' => filter_var_array($_SESSION)));
    }

    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function editAction()
    {
        $data = (new UserManager())->getUser($this->session->getUserVar('email'));

        return $this->render('user.twig', array('data' => $data));
    }

    /**
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function updateAction()
    {
        $data['username'] = (filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
        $data['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $data['password0'] = filter_input(INPUT_POST, 'oldpassword', FILTER_SANITIZE_STRING);
        $data['password'] = filter_input(INPUT_POST, 'newpassword', FILTER_SANITIZE_STRING);
        $data['password2'] = filter_input(INPUT_POST, 'passwordconfirm', FILTER_SANITIZE_STRING);
        $data['oldemail'] = $this->session->getUserVar('email');

        $userManager = new UserManager();
        $info = $userManager->getUser($data['oldemail']);
        $error = $this->verifyUser($data);

        if (!empty($data['password0']) and empty($data['password']) or empty($data['password0']) and !empty($data['password'])) {
            $error['password0'] = 'Veuillez remplir tous les champs !';
        }
        if (!empty($data['password0'] and password_verify($data['password0'], $info['password']) === false)) {
            $error['password0'] = "Mauvais password !";
        }
        if (!empty($error)) {

            return $this->render("user.twig", array("error" => $error, "data" => $info));
        }
        $data = $this->updateUser($data);
        $info = $userManager->getUser($data['oldemail']);
        $status = $this->session->checkStatus($info['status']);
        $this->session->createSession($info['id'], $info['username'], $info['email'], $status);
        $this->alert("Modifications enregistrées !");

        return $this->render("home.twig", array('session' => filter_var_array($_SESSION)));
    }

    /**
     * @param $data
     * @return array
     */
    public function verifyUser($data)
    {
        $error = [];
        $userManager = new UserManager();
        if (!empty($data['email']) and $userManager->checkUser($data['email']) === true) {
            $error['email'] = "Cet email est déjà utilisé !";
        }
        if (!empty($data['username']) and $userManager->checkUsername($data['username']) === true) {
            $error['username'] = "Ce Nom est déjà utilisé !";
        }
        if (!empty($data['password2']) and $data['password'] !== $data['password2']) {
            $error['password'] = "Vos passwords sont différents !";
        }
        return $error;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function updateUser($data)
    {
        $userManager = new UserManager();
        if (!empty($data['email'])) {
            $userManager->update($data['email'], 'email', $data['oldemail']);
            $data['oldemail'] = $data['email'];
        }
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);//encrypt the password before saving in the database
            $userManager->update($data['password'], 'password', $data['oldemail']);
        }
        if (!empty($data['username'])) {
            $userManager->update($data['username'], 'username', $data['oldemail']);
        }
        return $data;
    }
}
