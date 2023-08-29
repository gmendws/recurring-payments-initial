<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Agreement;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\Payer;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Plan;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Common\PayPalModel;
use PayPal\Rest\ApiContext;
use Spatie\FlareClient\Api;

class PayPalController extends Controller
{
    public function checkout()
    {
        try {
            $apiContext = $this->createApiContext();

            $userPremiumPlan = $this->createAndActivatePlan(
                $apiContext,
                [
                    'name' => 'User Premium Plan',
                    'description' => 'A premium plan for our users',
                    'frequency' => 'Month',
                    'frequencyInterval' => '1',
                    'cycles' => '12',
                    'amountValue' => '50',
                    'amountCurrency' => 'BRL'
                ]
            );

            return $this->createAgreement($apiContext, $userPremiumPlan);

        } catch (\Exception $e) {
            Log::error('Error during checkout: ' . $e->getMessage());
        }
    }

    private function createApiContext(): ApiContext
    {
        return new ApiContext(
            new OAuthTokenCredential(
                env('SANDBOX_PAYPAL_CLIENT_ID'),
                env('SANDBOX_PAYPAL_SECRET')
            )
        );
    }

    private function createAndActivatePlan(ApiContext $apiContext, array $config): Plan
    {
        $plan = $this->createPlan($apiContext, $config);
        $this->activatePlan($apiContext, $plan);
        return $plan;
    }

    function createPlan(ApiContext $apiContext, array $config)
    {
        $paymentDefinition = $this->createPaymentDefinition($config);

        $plan = new Plan();
        $plan->setName($config['name'])
            ->setDescription($config['description'])
            ->setType('FIXED');

        $plan->setPaymentDefinitions([$paymentDefinition]);

        $plan = $this->setMerchantPreferences($plan, url('/'));

        return $plan->create($apiContext);
    }

    function createPaymentDefinition($config): PaymentDefinition
    {
        $paymentDefinition = new PaymentDefinition();
        $paymentDefinition->setName('Pagamento Regular')
            ->setType('REGULAR')
            ->setFrequency($config['frequency'])
            ->setFrequencyInterval($config['frequencyInterval'])
            ->setCycles($config['cycles'])
            ->setAmount(new Currency([
                'value' => $config['amountValue'],
                'currency' => $config['amountCurrency']
            ]));

        return $paymentDefinition;
    }

    function setMerchantPreferences($plan, $baseUrl, $returnUrl = '/payment/success', $cancelUrl = '/payment/cancel')
    {
        $merchantPreferences = new MerchantPreferences();

        $merchantPreferences->setReturnUrl($baseUrl . $returnUrl)
            ->setCancelUrl($baseUrl . $cancelUrl)
            ->setAutoBillAmount('yes')
            ->setInitialFailAmountAction('CONTINUE')
            ->setMaxFailAttempts('0');

        $plan->setMerchantPreferences($merchantPreferences);

        return $plan;
    }

    public function activatePlan(ApiContext $apiContext, Plan $plan)
    {
        try {
            $patch = new Patch();

            $value = new PayPalModel('{
                "state":"ACTIVE"
            }');

            $patch->setOp('replace')
                ->setPath('/')
                ->setValue($value);

            $patchRequest = new PatchRequest();
            $patchRequest->addPatch($patch);

            $plan->update($patchRequest, $apiContext);

            return Plan::get($plan->getId(), $apiContext);
        } catch (\Exception $e) {
            Log::error('Error activating plan: ' . $e->getMessage());
        }
    }

    public function createAgreement(ApiContext $apiContext, Plan $plan)
    {
        try {
            $agreement = new Agreement();
            $agreement->setName('Assinatura para Plano User Premium')
                ->setDescription('Assinatura para Plano User Premium')
                ->setStartDate('2019-06-17T9:45:04Z');

            $agreement->setPlan($plan);

            $payer = new Payer();
            $payer->setPaymentMethod('paypal');
            $agreement->setPayer($payer);

            $agreement = $agreement->create($apiContext);

            $userAgreementID = $agreement->getId();

            return $this->redirectUser($agreement->getApprovalLink());
        } catch (\Exception $e) {
            Log::error('Error creating agreement: ' . $e->getMessage());
        }
    }

    public function executeAgreement(Request $request)
    {
        $apiContext = new ApiContext(
            new OAuthTokenCredential(
                env('PAYPAL_CLIENT_ID'),
                env('PAYPAL_SECRET')
            )
        );

        if ($request->query('success') === 'true') {
            $token = $request->query('token');
            $agreement = new Agreement();

            try {
                $agreement->execute($token, $apiContext);
            } catch (\Exception $ex) {
                return redirect()->route('payment-status')->with('error', 'Erro ao executar a assinatura.');
            }

            $agreement = Agreement::get($agreement->getId(), $apiContext);

            return redirect()->route('payment-status')->with('success', 'Assinatura ativada com sucesso.')->with('agreement', $agreement);
        } else {
            return redirect()->route('payment-status')->with('error', 'Usuário cancelou pagamento/não conseguiu pagar.');
        }
    }

    public function paymentStatus()
    {
        return view('payment-status');
    }

    protected function redirectUser($url)
    {
        return redirect($url);
    }

}
