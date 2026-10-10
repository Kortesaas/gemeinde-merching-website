<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $feedback = is_array($this->session()->get('contact.context'));

        return [
            'contact_route_id' => ['required', 'integer', Rule::exists('contact_routes', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'contact_name' => ['required', 'string', 'max:150', 'regex:/^[^\r\n\x00-\x1f]+$/u'],
            'contact_email' => ['required', 'email:rfc', 'max:254', 'regex:/^[^\r\n]+$/'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+() .\/-]+$/'],
            'contact_subject' => [$feedback ? 'nullable' : 'required', 'string', 'max:200', 'regex:/^[^\r\n\x00-\x1f]+$/u'],
            'contact_street' => [$feedback ? 'nullable' : 'required', 'string', 'max:150', 'regex:/^[^\r\n\x00-\x1f]+$/u'],
            'contact_postal_code' => [$feedback ? 'nullable' : 'required', 'string', 'max:20', 'regex:/^[\pL\pN -]+$/u'],
            'contact_city' => [$feedback ? 'nullable' : 'required', 'string', 'max:150', 'regex:/^[^\r\n\x00-\x1f]+$/u'],
            'contact_reply_by' => [$feedback ? 'nullable' : 'required', Rule::in(['email', 'post'])],
            'contact_privacy' => ['accepted'],
            'contact_message' => [$feedback ? 'required' : 'nullable', 'string', ...($feedback ? ['min:10'] : []), 'max:10000'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_nonce' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return list<callable(Validator):void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $issued = (int) $this->session()->get('contact.issued_at', 0);
            $nonce = (string) $this->session()->get('contact.nonce', '');
            $elapsed = now()->getTimestamp() - $issued;
            if ($nonce === '' || ! hash_equals($nonce, (string) $this->input('form_nonce')) || $elapsed < (int) config('contact.minimum_seconds') || $elapsed > (int) config('contact.maximum_seconds')) {
                $validator->errors()->add('general', 'Das Formular ist noch nicht bereit oder abgelaufen. Bitte laden Sie das Formular neu und versuchen Sie es erneut.');
            }
            $body = (string) $this->input('contact_message');
            if (preg_match_all('~https?://~i', $body) > 5) {
                $validator->errors()->add('contact_message', 'Bitte begrenzen Sie die Anzahl der Internetadressen in der Nachricht.');
            }
        }];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['contact_route_id' => 'Empfänger', 'contact_name' => 'Name', 'contact_email' => 'E-Mail', 'contact_phone' => 'Telefonnummer', 'contact_subject' => 'Betreff', 'contact_street' => 'Straße und Hausnummer', 'contact_postal_code' => 'Postleitzahl', 'contact_city' => 'Ort', 'contact_reply_by' => 'Antwortweg', 'contact_privacy' => 'Datenschutzbestätigung', 'contact_message' => 'Nachricht'];
    }
}
