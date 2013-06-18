<?php
namespace MssSendza\Service;
use InvalidArgumentException,
    RuntimeException,
    MssSendza\Connection as SendzaConnection,
    Zend\OAuth;

class Sendza extends SendzaConnection
{
    protected $data   = array();
    protected $tokens = array();

    protected $commands = array(
        'accounts.create'      => 'MssSendza\Command\AccountsCreate',
        'accounts.get'         => 'MssSendza\Command\AccountsGet',
        'accounts.get.email'   => 'MssSendza\Command\AccountsGetEmail',
        'accounts.list'        => 'MssSendza\Command\AccountsList',
        'responses.list'       => 'MssSendza\Command\ResponsesList',
        'responses.list.date'  => 'MssSendza\Command\ResponsesListDate',
        'responses.list.group' => 'MssSendza\Command\ResponsesListGroup',
        'users.create'         => 'MssSendza\Command\UsersCreate',
        'users.list'           => 'MssSendza\Command\UsersList',
        'users.list.email'     => 'MssSendza\Command\UsersListEmail',
    );

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
        return $this;
    }

    public function execute($cmd, array $replace = array())
    {
        if (!array_key_exists($cmd, $this->commands)) {
            throw new InvalidArgumentException(sprintf(
                'no such command "%s" is registered',
                $cmd
            ));
        }

        $cmd  = new $this->commands[$cmd];
        $data = array();
        if ($cmd->getDataKey()) {
            foreach($cmd->getRequiredData() as $required) {
                if (!array_key_exists($required, $this->data)) {
                    throw new InvalidArgumentException(sprintf(
                        'missing required data: "%s"',
                        $required
                    ));
                }
            }

            $data = array($cmd->getDataKey() => $this->data);
        }

        return $this->go($data, $this->parseEndpoint($cmd->getEndpoint()), $cmd->getMethod());
    }

    protected function parseEndpoint($endpoint)
    {
        $tokens = $this->tokens;

        if (preg_match_all('/%(\w+)%/', $endpoint, $matches)) {
            foreach ($matches[1] as $match) {
                if (!isset($tokens[$match])) {
                    throw new RuntimeException(sprintf(
                        'found token "%s" with no matching replacement',
                        $match
                    ));
                }
                $endpoint = str_replace("%{$match}%", $tokens[$match], $endpoint);
            }
        }
        return $endpoint;
    }
}