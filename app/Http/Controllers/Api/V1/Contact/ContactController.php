<?php

namespace App\Http\Controllers\Api\V1\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Contact\ListContactsRequest;
use App\Http\Requests\Api\V1\Contact\StoreContactRequest;
use App\Http\Requests\Api\V1\Contact\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactCollection;
use App\Http\Resources\Api\V1\ContactResource;
use App\Services\Contact\ContactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function index(ListContactsRequest $request): JsonResponse
    {
        $contacts = $this->contactService->list(
            $request->filters(),
            $request->perPage(),
            (int) $request->user()->id,
        );

        return ApiResponse::success(
            new ContactCollection($contacts),
            'Contacts retrieved.',
        );
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contactService->create($request->validated(), (int) $request->user()->id);

        return ApiResponse::success(
            new ContactResource($contact),
            'Contact created.',
            201,
        );
    }

    public function show(Request $request, int $contact): JsonResponse
    {
        $model = $this->contactService->find($contact, (int) $request->user()->id);

        return ApiResponse::success(
            new ContactResource($model),
            'Contact retrieved.',
        );
    }

    public function update(UpdateContactRequest $request, int $contact): JsonResponse
    {
        $model = $this->contactService->update(
            $this->contactService->find($contact, (int) $request->user()->id),
            $request->validated(),
        );

        return ApiResponse::success(
            new ContactResource($model),
            'Contact updated.',
        );
    }

    public function destroy(Request $request, int $contact): JsonResponse
    {
        $this->contactService->delete(
            $this->contactService->find($contact, (int) $request->user()->id),
        );

        return ApiResponse::success(null, 'Contact deleted.');
    }
}
