// Mirrors App\Http\Resources\Api\V1\ProviderResource (apps/backend).
export interface ProviderListItem {
  id: string;
  business_name: string;
  commercial_registration_number: string | null;
  tax_number: string | null;
  contact_phone: string;
  contact_email: string | null;
  status: string;
  rating: string | null;
  rejection_reason: string | null;
  suspension_reason: string | null;
  approved_at: string | null;
  created_at: string;
}

// Mirrors App\Http\Resources\Api\V1\DocumentResource.
export interface DocumentItem {
  id: string;
  document_type: string;
  document_number: string | null;
  issued_at: string | null;
  expires_at: string | null;
  verification_status: string;
  rejection_reason: string | null;
  verified_at: string | null;
  original_filename: string;
  created_at: string;
}

// Mirrors App\Domain\Ledger\Actions\GetProviderBalanceAction's return shape.
export interface ProviderBalance {
  pending_balance: number;
  available_balance: number;
  settled_balance: number;
  total_payable: number;
}

// Mirrors App\Http\Resources\Api\V1\ProviderBankAccountResource.
export interface BankAccount {
  id: string;
  account_holder_name: string;
  iban: string;
  bank_name: string;
  verified: boolean;
  verified_at: string | null;
  created_at: string;
  updated_at: string;
}
