<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractDecision;
use App\Models\Customer;
use App\Services\Contracts\ContractPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ContractDocumentController extends Controller
{
    public function print(Contract $contract): View
    {
        $contract = $this->loadContract($contract);

        return view('contracts.print', [
            'contract' => $contract,
            'signature' => $this->acceptedSignature($contract),
            'downloadUrl' => "/app/contracts/{$contract->id}/download",
            'portal' => 'admin',
        ]);
    }

    public function download(Contract $contract, ContractPdfRenderer $renderer): Response
    {
        $contract = $this->loadContract($contract);

        return $this->pdfResponse($contract, $renderer);
    }

    public function customerPrint(Request $request, Contract $contract): View
    {
        $this->authorizeCustomerContract($request, $contract);
        $contract = $this->loadContract($contract);

        return view('contracts.print', [
            'contract' => $contract,
            'signature' => $this->acceptedSignature($contract),
            'downloadUrl' => "/app/customer/contracts/{$contract->id}/download",
            'portal' => 'customer',
        ]);
    }

    public function customerDownload(Request $request, Contract $contract, ContractPdfRenderer $renderer): Response
    {
        $this->authorizeCustomerContract($request, $contract);
        $contract = $this->loadContract($contract);

        return $this->pdfResponse($contract, $renderer);
    }

    private function loadContract(Contract $contract): Contract
    {
        return $contract->load([
            'customer',
            'site',
            'service',
            'servicePackage',
            'addendums' => fn ($query) => $query->orderBy('number'),
            'contractDecisions' => fn ($query) => $query->latest(),
        ]);
    }

    private function acceptedSignature(Contract $contract): ?ContractDecision
    {
        return $contract->contractDecisions
            ->first(fn (ContractDecision $decision): bool => $decision->decision === 'accepted' && filled($decision->signature_text));
    }

    private function authorizeCustomerContract(Request $request, Contract $contract): void
    {
        abort_unless($request->user()?->role === 'customer', 403);

        $customer = Customer::query()
            ->where('user_id', $request->user()->id)
            ->orWhere(fn ($query) => $query
                ->whereNull('user_id')
                ->where('email', $request->user()->email))
            ->first();

        abort_unless($customer && (int) $contract->customer_id === (int) $customer->id, 404);
    }

    private function pdfResponse(Contract $contract, ContractPdfRenderer $renderer): Response
    {
        $pdf = $renderer->render($contract, $this->acceptedSignature($contract));
        $filename = $this->filename($contract);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    private function filename(Contract $contract): string
    {
        $reference = preg_replace('/[^A-Za-z0-9._-]+/', '-', $contract->reference) ?: 'contract';

        return trim($reference, '-').'.pdf';
    }
}
