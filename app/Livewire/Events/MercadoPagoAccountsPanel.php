<?php

namespace App\Livewire\Events;

use App\Enums\Events\MercadoPagoEnvironment;
use App\Models\MercadoPagoAccount;
use App\Services\Events\MercadoPago\MercadoPagoClientFactory;
use Illuminate\Validation\Rule;
use Livewire\Component;
use MercadoPago\Exceptions\MPApiException;

final class MercadoPagoAccountsPanel extends Component
{
    public ?string $editingAccountId = null;

    public string $formLabel = '';

    public string $formPublicKey = '';

    public string $formAccessToken = '';

    public string $formEnvironment = 'sandbox';

    public ?string $connectionMessage = null;

    public function startCreateAccount(): void
    {
        $this->editingAccountId = null;
        $this->formLabel = '';
        $this->formPublicKey = '';
        $this->formAccessToken = '';
        $this->formEnvironment = MercadoPagoEnvironment::Sandbox->value;
        $this->connectionMessage = null;
        $this->resetValidation();
    }

    public function startEditAccount(string $accountId): void
    {
        $account = $this->findOwnedAccount($accountId);
        $this->editingAccountId = $account->id;
        $this->formLabel = $account->label;
        $this->formPublicKey = $account->public_key;
        $this->formAccessToken = '';
        $this->formEnvironment = $account->environment->value;
        $this->connectionMessage = null;
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        $this->validate($this->accountRules());

        $payload = [
            'label' => $this->formLabel,
            'public_key' => $this->formPublicKey,
            'environment' => MercadoPagoEnvironment::from($this->formEnvironment),
        ];

        if ($this->formAccessToken !== '') {
            $payload['access_token'] = $this->formAccessToken;
        }

        if ($this->editingAccountId !== null) {
            $account = $this->findOwnedAccount($this->editingAccountId);
            if ($this->formAccessToken === '') {
                unset($payload['access_token']);
            }
            $account->update($payload);
            session()->flash('events_mp_notice', 'Conta Mercado Pago atualizada.');
        } else {
            if ($this->formAccessToken === '') {
                $this->addError('formAccessToken', 'Informe o access token.');

                return;
            }
            $payload['owner_id'] = auth()->id();
            MercadoPagoAccount::query()->create($payload);
            session()->flash('events_mp_notice', 'Conta Mercado Pago criada.');
        }

        $this->startCreateAccount();
    }

    public function deleteAccount(string $accountId): void
    {
        $account = $this->findOwnedAccount($accountId);
        $account->delete();
        session()->flash('events_mp_notice', 'Conta removida.');
        $this->startCreateAccount();
    }

    public function testConnection(string $accountId): void
    {
        $account = $this->findOwnedAccount($accountId);

        try {
            app(MercadoPagoClientFactory::class)->userClient($account)->get();
            $this->connectionMessage = 'Conexão OK com o Mercado Pago.';
        } catch (MPApiException $exception) {
            $this->connectionMessage = 'Falha na conexão. Verifique as credenciais.';
            report($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function accountRules(): array
    {
        return [
            'formLabel' => ['required', 'string', 'max:120'],
            'formPublicKey' => ['required', 'string', 'max:255'],
            'formAccessToken' => ['nullable', 'string', 'max:500'],
            'formEnvironment' => ['required', Rule::enum(MercadoPagoEnvironment::class)],
        ];
    }

    private function findOwnedAccount(string $accountId): MercadoPagoAccount
    {
        return MercadoPagoAccount::query()
            ->whereKey($accountId)
            ->where('owner_id', auth()->id())
            ->firstOrFail();
    }

    public function render()
    {
        $accounts = MercadoPagoAccount::query()
            ->where('owner_id', auth()->id())
            ->orderBy('label')
            ->get();

        return view('livewire.events.mercado-pago-accounts-panel', [
            'accounts' => $accounts,
            'webhookUrl' => rtrim((string) config('events.hub_public_url'), '/').'/webhooks/mercadopago',
        ]);
    }
}
