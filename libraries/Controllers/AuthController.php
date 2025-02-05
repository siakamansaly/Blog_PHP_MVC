<?php

namespace Blog\Controllers;

use Blog\Controllers\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Blog\Controllers\Globals;

class AuthController extends Controller
{
    protected $modelName = \Blog\Models\User::class;
    private $data = [];
    private string $password;
    public $errorMessage = "";

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Redirects user if not logged in
     * @return void
     */
    public function forceLogin()
    {
        if (empty($this->session->get('auth'))) {
            $this->redirect("/error/401");
        }
    }

    /**
     * Checks if the user is logged in
     * @return bool
     */
    public function isLogin(): bool
    {
        if ($this->session->get('auth') <> "") {
            return true;
        }
        return false;
    }

    /**
     * Checks if the user is logged in
     * @return bool
     */
    public function isAdmin(): bool
    {
        if ($this->session->get('userType') == 'admin') {
            return true;
        }
        return false;
    }

    /**
     * Checks if the user is an administrator
     * @return true or redirect to homepage
     */
    public function forceAdmin()
    {
        switch ($this->session->get('userType')) {

            case $this->session->isStarted() === false:
                $this->redirect("/error/401");
                break;

            case $this->session->get('userType') == 'admin':
                return true;
                break;

            default:
                $this->redirect("/error/403");
                break;
        }
    }

    /**
     * Function allowing (or not) the user to log in
     * @return json
     */
    public function login()
    {
        if (empty($this->var->request->all())) {
            $this->redirect("/error/405");
        }
        $this->data = [];
        $user = "";
        $error = 0;
        $this->password = "";
        $json = [];
        $success = "";
        $message = "";
        $this->errorMessage = "";

        $this->data['email'] = $this->sanitize($this->var->request->get('emailLogin'));
        $this->data['password'] = $this->sanitize($this->var->request->get('passwordLogin'));

        // Check if values not empty
        if (empty($this->data['email']) || empty($this->data['password'])) {
            $this->errorMessage .= $this->liAlert("Merci de renseigner tous les champs obligatoire !");
            $error++;
        }
        // Check if email exist
        switch ($this->model->getEmail($this->data['email'])) {
            case 1:
                $user = $this->model->read($this->data['email'], 'email');
                // Check status user
                switch ($user['status']) {
                    case 1:
                        $this->password = $user['password'];
                        // Check password
                        if ((empty(password_verify($this->data['password'], $this->password)))) {
                            $this->errorMessage .= $this->liAlert("L'identifiant ou le mot de passe est incorrect !");
                            $error++;
                        }
                        break;
                    default:
                        $this->errorMessage .= $this->liAlert("Ce compte n'est pas actif. Merci de contacter l'administrateur.");
                        $error++;
                        break;
                }
                break;
            default:
                $error++;
                $this->errorMessage = $this->liAlert("Ce compte n'existe pas.");
                break;
        }


        $this->errorMessage = $this->ulAlert($this->errorMessage);
        // Construct and send result JSON
        switch ($error) {
            case 0:
                $message = $this->divAlert("Connexion réussie.", "success");
                $success = true;
                // Assign session variables
                $this->session->set('firstName', $user['firstName']);
                $this->session->set('lastName', $user['lastName']);
                $this->session->set('email', $this->data['email']);
                $this->session->set('userType', $user['userType']);
                $this->session->set('auth', "true");
                $this->session->set('regitrationDate', $user['regitrationDate']);
                $this->session->set('lastConnectionDate', $user['lastConnectionDate']);
                $this->session->set('id', $user['id']);
                // Update lastConnectionDate
                $this->data['lastConnectionDate'] = date('Y-m-d H:i:s');
                $this->model->update($this->data['email'], ["lastConnectionDate" => $this->data['lastConnectionDate']], 'email');
                break;

            default:
                $message = $this->divAlert($this->errorMessage, "danger");
                $success = false;
                break;
        }

        $json['success'] = $success;
        $json['message'] = $message;

        $response = new JsonResponse($json);
        $response->send();
    }

    /**
     * Function logout
     * @return void
     */

    public function logout()
    {
        $this->session->remove('firstName');
        $this->session->remove('lastName');
        $this->session->remove('email');
        $this->session->remove('userType');
        $this->session->remove('auth', "true");
        $this->session->remove('regitrationDate');
        $this->session->remove('lastConnectionDate');
        $this->session->remove('id');

        $this->redirect("/");
    }

    /**
     * Function register
     * @return json
     */
    public function register()
    {
        if (empty($this->var->request->all())) {
            $this->redirect("/error/405");
        }
        $this->data = [];
        $passwordRepeat = "";
        $json = [];
        $success = "";
        $message = "";
        $error = 0;
        $this->errorMessage = "";

        $this->data['firstName'] = $this->sanitize($this->var->request->get('firstName'));
        $this->data['lastName'] = $this->sanitize($this->var->request->get('lastName'));
        $this->data['email'] = $this->sanitize($this->var->request->get('emailRegister'));
        $this->data['password'] = $this->sanitize($this->var->request->get('passwordRegister'));
        $this->data['regitrationDate'] = date('Y-m-d H:i:s');
        $passwordRepeat = $this->sanitize($this->var->request->get('passwordRepeat'));

        if ($this->model->getEmail($this->data['email']) == 1) {
            $this->errorMessage .= $this->liAlert("L'adresse email renseignée est déja inscrite !");
            $error++;
        }
        switch ($this->data['password'] <> $passwordRepeat) {
            case true:
                $this->errorMessage .= $this->liAlert("Les mots de passe saisies ne sont pas identique !");
                $error++;
                break;
            default:
                $this->data['password'] = password_hash($this->data['password'], PASSWORD_DEFAULT);
                break;
        }


        $this->errorMessage = $this->ulAlert($this->errorMessage);

        switch ($error) {
            case 0:
                $this->model->insert($this->data);

                $ENV = new Globals;
                $titleWebSite = $ENV->env("TITLE_WEBSITE");

                $this->data['subject'] = $titleWebSite . " - Inscription en attente d'approbation";
                $this->data['message'] = "Votre inscription a bien été pris en compte. L'administrateur validera votre inscription très rapidemment. A bientôt !";
                $message = $this->divAlert("Inscription réussie. <br/> Patience... Votre compte est en attente de validation par l'administrateur.", "success");
                $this->sendMessage($this->data);
                $success = true;
                break;
            default:
                $message = $this->divAlert($this->errorMessage, "danger");
                $success = false;
                break;
        }

        $json['success'] = $success;
        $json['message'] = $message;

        $response = new JsonResponse($json);
        $response->send();
    }
    /**
     * Function lostPassword
     * @return json
     */
    public function lostPassword()
    {
        if (empty($this->var->request->all())) {
            $this->redirect("/error/405");
        }
        $emailLostPassword = "";
        $this->data = [];
        $json = [];
        $success = "";
        $message = "";
        $error = 0;
        $this->errorMessage = "";

        $emailLostPassword = $this->sanitize($this->var->request->get('emailLostPassword'));

        if ($this->model->getEmail($emailLostPassword) == 0) {
            $this->errorMessage .= $this->liAlert("L'adresse email renseignée n'est pas inscrite !");
            $error++;
        }
        $this->errorMessage = $this->ulAlert($this->errorMessage);

        switch ($error) {
            case 0:
                $message = $this->divAlert("Un lien de réinitialisation de mot de passe a été envoyé sur votre boite mail.", "success");
                $success = true;
                $this->data['token'] = uniqid('', false);
                $this->model->update($emailLostPassword, $this->data, 'email');

                $ENV = new Globals;
                $titleWebSite = $ENV->env("TITLE_WEBSITE");
                $this->data['subject'] = $titleWebSite . " - Réinitialisation mot de passe";
                $this->data['message'] = "Voici un lien pour réinitialiser votre mot de passe : " . $this->var->server->get('HTTP_REFERER') . "renew/" . $this->data['token'];
                $this->data['email'] = $emailLostPassword;
                $this->data['firstName'] = "";
                $this->data['lastName'] = "";
                $this->sendMessage($this->data);
                break;

            default:
                $message = $this->divAlert($this->errorMessage, "danger");
                $success = false;
                break;
        }

        $json['success'] = $success;
        $json['message'] = $message;

        $response = new JsonResponse($json);
        $response->send();
    }

    /**
     * Function lostPassword
     * @return json
     */
    public function savePassword()
    {
        if (empty($this->var->request->all())) {
            $this->redirect("/error/405");
        }
        $this->data = [];
        $json = [];
        $success = "";
        $message = "";
        $error = 0;
        $passwordOld = "";
        $passwordRepeat = "";
        $idUser = "";
        $this->errorMessage = "";

        $this->data['password'] = $this->sanitize($this->var->request->get('passwordRenew'));
        $idUser = $this->sanitize($this->var->request->get('id'));
        $passwordRepeat = $this->sanitize($this->var->request->get('passwordRepeatRenew'));
        $user = $this->model->read($idUser);
        if (!empty($this->var->request->get('passwordOldChange'))) {
            $passwordOld = $this->var->request->get('passwordOldChange');
            $this->password = $user['password'];

            // Check password
            if ((empty(password_verify($passwordOld, $this->password)))) {
                $this->errorMessage .= $this->liAlert("L'ancien mot de passe est incorrect !");
                $error++;
            }
        }
        if ($this->data['password'] <> $passwordRepeat) {
            $this->errorMessage .= $this->liAlert("Les mots de passe saisies ne sont pas identique !");
            $error++;
        }
        $this->errorMessage = $this->ulAlert($this->errorMessage);

        switch ($error) {
            case 0:
                $message = $this->divAlert("Votre mot de passe a bien été modifié.", "success");
                $success = true;
                $this->data['password'] = password_hash($this->data['password'], PASSWORD_DEFAULT);
                $this->data['token'] = NULL;
                $this->model->update($idUser, $this->data);

                $ENV  = new Globals;
                $titleWebSite = $ENV->env("TITLE_WEBSITE");

                $this->data['subject'] = $titleWebSite . " - Votre mot de passe a bien été changé";
                $this->data['message'] = "Votre mot de passe a bien été changé.";

                $this->data['email'] = $user['email'];
                $this->data['firstName'] = $user['firstName'];
                $this->data['lastName'] = $user['lastName'];
                $this->sendMessage($this->data);
                break;

            default:
                $message = $this->divAlert($this->errorMessage, "danger");
                $success = false;
                break;
        }

        $json['success'] = $success;
        $json['message'] = $message;

        $response = new JsonResponse($json);
        $response->send();
    }
}
