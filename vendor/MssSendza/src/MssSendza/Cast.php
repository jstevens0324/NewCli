<?php

namespace MssSendza;

use InvalidArgumentException,
    MssSendza\Recipient\AbstractRecipient;

class Cast
{
    const TYPE_EMAIL = 'email';
    const TYPE_SMS   = 'sms';
    const TYPE_PHONE = 'phone';

    private $validTypes = array(
        self::TYPE_EMAIL,
        self::TYPE_SMS,
        self::TYPE_PHONE
    );

    /**
     * @var string
     */
    private $subject;

    /**
     * @var array
     */
    private $recipients = array();

    /**
     * @var array
     */
    private $bodies = array();

    /**
     * @var array
     */
    private $recordResponse = array();

	/**
     * Get subject
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

	/**
     * Set subject
     *
     * @param string $subject
     * @return MssSendza\Cast
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Add recipient
     *
     * @param MssSendza\Recipient\AbstractRecipient $recipient
     * @return MssSendza\Cast
     */
    public function addRecipient(AbstractRecipient $recipient)
    {
        $this->recipients[$recipient->getType()] = $recipient;
        return $this;
    }

    /**
     * Clear recipients
     *
     * @return MssSendza\Cast
     */
    public function clearRecipients()
    {
        $this->recipients = array();
        return $this;
    }

	/**
     * Get recipients
     *
     * @return array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

	/**
     * Set recipients
     *
     * @param array $recipients
     * @return MssSendza\Cast
     */
    public function setRecipients(array $recipients)
    {
        $this->clearRecipients();

        foreach($recipients as $recipient) {
            $this->addRecipient($recipient);
        }
        return $this;
    }

    /**
     * Get bodies
     *
     * @return array
     */
    public function getBodies()
    {
        return $this->bodies;
    }

	/**
     * Get body by type
     *
     * @param string $type
     * @return array
     */
    public function getBody($type = self::TYPE_EMAIL)
    {
        return $this->bodies[$type];
    }

	/**
     * Set body by type
     *
     * @param array  $bodies
     * @param string $type
     * @return MssSendza\Cast
     */
    public function setBody($body, $type = self::TYPE_EMAIL)
    {
        if (!in_array($type, $this->validTypes)) {
            throw new InvalidArgumentException('invalid body type');
        }

        $this->bodies[$type] = $body;
        return $this;
    }

    /**
     * Set recordResponse
     *
     * @param string $tag
     * @param array  $replace
     * @return MssSendza\Cast
     */
    public function setRecordResponse($tag, array $replace = array())
    {
        $this->recordResponse = array(
            'tag'     => $tag,
            'replace' => $replace
        );
        return $this;
    }

	/**
     * Get recordResponse
     *
     * @return array
     */
    public function getRecordResponse()
    {
        return $this->recordResponse;
    }
}