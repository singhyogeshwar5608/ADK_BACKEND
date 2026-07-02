<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    /**
     * Update KYC documents for the authenticated member
     */
    public function update(Request $request)
    {
        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'bankAccountNumber' => 'nullable|string|max:50',
            'aadharNumber' => 'nullable|string|max:20',
            'panNumber' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Debug logging
            \Log::info('KYC Update Request Data: ' . json_encode($request->all()));
            
            // Update KYC numbers
            if ($request->has('bankAccountNumber')) {
                \Log::info('Updating bank account number: ' . $request->bankAccountNumber);
                $member->bank_account_number = $request->bankAccountNumber;
            }
            if ($request->has('aadharNumber')) {
                \Log::info('Updating aadhar number: ' . $request->aadharNumber);
                $member->aadhar_number = $request->aadharNumber;
            }
            if ($request->has('panNumber')) {
                \Log::info('Updating pan number: ' . $request->panNumber);
                $member->pan_number = $request->panNumber;
            }

            // Handle image URLs from Cloudinary
            $this->handleImageUrl($request, $member, 'bankAccountImage', 'bank_account_image');
            $this->handleImageUrl($request, $member, 'aadharImage', 'aadhar_image');
            $this->handleImageUrl($request, $member, 'panImage', 'pan_image');

            // Update KYC status if all documents are uploaded
            if ($this->isKycComplete($member)) {
                $member->kyc_status = 'PENDING'; // Set to pending for admin verification
            }

            \Log::info('Member before save: ' . json_encode($member->toArray()));
            $member->save();
            \Log::info('Member after save: ' . json_encode($member->toArray()));

            return response()->json([
                'success' => true,
                'message' => 'KYC documents updated successfully',
                'data' => [
                    'kyc' => [
                        'bankAccount' => [
                            'number' => $member->bank_account_number,
                            'image' => $member->bank_account_image,
                        ],
                        'aadharCard' => [
                            'number' => $member->aadhar_number,
                            'image' => $member->aadhar_image,
                        ],
                        'panCard' => [
                            'number' => $member->pan_number,
                            'image' => $member->pan_image,
                        ],
                        'status' => $member->kyc_status,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update KYC documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle image URL from Cloudinary
     */
    private function handleImageUrl(Request $request, Member $member, string $requestField, string $dbField)
    {
        if ($request->has($requestField)) {
            $imageUrl = $request->input($requestField);
            
            // Validate that it's a valid URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid image URL for ' . $requestField);
            }

            // Validate that it's a Cloudinary URL (optional but recommended)
            if (! str_contains(strtolower($imageUrl), 'cloudinary.com')) {
                throw new \Exception('Image must be uploaded to Cloudinary for ' . $requestField);
            }

            // No need to delete old image from storage since we're using Cloudinary
            // Just update the database with the new Cloudinary URL
            $member->$dbField = $imageUrl;
            
            \Log::info('Updated ' . $dbField . ' with Cloudinary URL: ' . $imageUrl);
        }
    }

    /**
     * Check if KYC is complete (all documents uploaded)
     */
    private function isKycComplete(Member $member): bool
    {
        return !empty($member->bank_account_number) && 
               !empty($member->bank_account_image) &&
               !empty($member->aadhar_number) && 
               !empty($member->aadhar_image) &&
               !empty($member->pan_number) && 
               !empty($member->pan_image);
    }

    /**
     * Get KYC status for the authenticated member
     */
    public function status(Request $request)
    {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'kyc' => [
                    'bankAccount' => [
                        'number' => $member->bank_account_number,
                        'image' => $this->publicKycImageUrl($member->bank_account_image),
                    ],
                    'aadharCard' => [
                        'number' => $member->aadhar_number,
                        'image' => $this->publicKycImageUrl($member->aadhar_image),
                    ],
                    'panCard' => [
                        'number' => $member->pan_number,
                        'image' => $this->publicKycImageUrl($member->pan_image),
                    ],
                    'status' => $member->kyc_status,
                    'rejectionReason' => $member->kyc_rejection_reason,
                    'verifiedAt' => $member->kyc_verified_at?->toIso8601String(),
                ]
            ]
        ]);
    }

    /**
     * Return full URL for KYC image: Cloudinary/abs URL as stored, or legacy storage path.
     */
    private function publicKycImageUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $trimmed = trim($stored);
        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        return asset('storage/'.$trimmed);
    }
}
