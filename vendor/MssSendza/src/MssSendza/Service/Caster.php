<?php
namespace MssSendza\Service;
use InvalidArgumentException,
    RuntimeException,
    MssSendza\Connection as SendzaConnection,
    MssSendza\Recipient\Collection as RecipientCollection,
    MssSendza\Cast,
    Zend\Http\Request;

class Caster extends SendzaConnection
{
    public function send(Cast $cast, $accountId = null)
    {
        $data = $this->prepareCast($cast);

        if ($accountId) {
            $endpoint = sprintf('Cast/Accounts/%s/Casts', $accountId);
        } else {
            $endpoint = 'Cast/Casts';
        }

        $result = $this->go($data, $endpoint, Request::METHOD_POST);

        return $result;
    }

    protected function prepareCast(Cast $cast)
    {
        $subject = $cast->getSubject();

        if (empty($subject)) {
            throw new RuntimeException('no subject was set');
        }

        $data = array('Subject' => $subject);
        foreach($cast->getBodies() as $type => $body) {
            if (empty($body)) {
                continue;
            }

            $recipients = $cast->getRecipients($type);
            if (empty($recipients)) {
                continue;
            }

            $data['Bodies']['Body'][] = array(
                'Type' => $type,
                'Value' => $body
            );

            $response = $cast->getRecordResponse();
            if (!empty($response)) {
                $rdata = array('Tag' => $response['tag']);

                foreach($response['replace'] as $find => $value) {
                    $rdata['Responses']['Response'][] = array(
                        'Tag'   => $find,
                        'Value' => $value
                    );
                }

                $data['Extend']['RecordResponse'] = $rdata;
            }

            foreach($recipients as $recipient) {
                $data['Recipients']['Recipient'][] = $recipient->getData();
            }
        }

        return array('Message' => $data);
    }
}
