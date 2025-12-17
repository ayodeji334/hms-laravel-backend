<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AnteNatalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\GeneralExaminationController;
use App\Http\Controllers\LabourRecordController;
use App\Http\Controllers\LabRequestController;
use App\Http\Controllers\LabTestResultTemplateController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OperationRecordController;
use App\Http\Controllers\OrganisationAndHmoController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhysicalExaminationController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductManufacturerController;
use App\Http\Controllers\ProductPurchaseController;
use App\Http\Controllers\ProductSalesController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\RoomCategoryController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitationController;
use App\Http\Controllers\VitalSignController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'staffLogin']);

Route::middleware("auth:sanctum")->group(function () {
    Route::post('auth/update-password', [AuthController::class, 'updateStaffPassword']);

    Route::get('/logout', function (Request $request) {
        return $request->user()->tokens()->delete();;
    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('patients')->group(function () {
        Route::patch('/{id}/update-medical-bio', [PatientController::class, 'updateMedicalBio']);
        Route::get('/', [PatientController::class, 'findAll']);
        Route::get('/patient/{id}', [PatientController::class, 'getOne'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER,DOCTOR,NURSE');
        Route::get('{id}', [PatientController::class, 'findOne'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER,DOCTOR,NURSE');
        Route::get('{id}/change-status/{status}', [PatientController::class, 'toggleStatus'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER');
        Route::get('/payments', [PatientController::class, 'findPatientControllerPayments'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER');
        Route::post('/', [PatientController::class, 'create'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER');
        Route::post('/upload', [PatientController::class, 'uploadPatients'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER');
        Route::patch('{id}', [PatientController::class, 'update'])->middleware('role:SUPER-ADMIN,ADMIN,RECORD-KEEPER');
        Route::get('patient/{id}/visitations', [PatientController::class, 'visitations']);
        Route::get('patient/{id}/prescriptions', [PatientController::class, 'prescriptions']);
        Route::get('patient/{id}/treatments', [PatientController::class, 'treatments']);
        Route::get('patient/{id}/lab-requests', [PatientController::class, 'labRequests']);
        Route::get('patient/{id}/payments', [PatientController::class, 'payments']);
        Route::get('/patient/{patient}/latest-records', [PatientController::class, 'latestMedicalRecords']);
        // Route::delete('{id}', [PatientController::class, 'delete']);
    });

    Route::prefix('visitations')->group(function () {
        Route::get('/', [VisitationController::class, 'findAll'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR,NURSE');
        Route::get('/today', [VisitationController::class, 'findAllAppointmentsForToday'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR,NURSE');
        Route::get('{id}', [VisitationController::class, 'findOne'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR');
        Route::get('{id}/accept', [VisitationController::class, 'accept'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR');
        Route::get('{id}/{status}', [VisitationController::class, 'approveOrCancel'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR,NURSE');
        Route::post('/', [VisitationController::class, 'create'])->middleware('role:SUPER-ADMIN,ADMIN,NURSE');
        Route::post('{id}/add-recommended-tests', [VisitationController::class, 'createRecommendedTests'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR');
        Route::post('{id}/consultation-report', [VisitationController::class, 'addConsultationReport'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR');
        Route::patch('{id}/reschedule', [VisitationController::class, 'reschedule'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR,NURSE');
        Route::patch('{id}', [VisitationController::class, 'update'])->middleware('role:SUPER-ADMIN,ADMIN,DOCTOR,NURSE');
        // Route::delete('{id}', [VisitationController::class, 'delete']);
    });

    Route::prefix('products')->group(function () {
        // Route::post('/products/upload', [ProductController::class, 'upload']);
        Route::get('/search', [ProductController::class, 'searchProducts']);
        Route::get('/search-products', [ProductController::class, 'searchProductByName']);
        Route::get('/', [ProductController::class, 'findAll']);
        Route::get('/inventory', [ProductController::class, 'getInventoryRecords'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('/out-of-stock', [ProductController::class, 'getOutOfStockProducts'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('/damaged', [ProductController::class, 'getDamagedProducts'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('/expired', [ProductController::class, 'getExpiredProducts'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('/product/{id}', [ProductController::class, 'findOne'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/mark-as-damaged', [ProductController::class, 'markDamaged'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/un-mark-as-damaged', [ProductController::class, 'unMarkDamaged'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/mark-as-expired', [ProductController::class, 'markExpired'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/un-mark-as-expired', [ProductController::class, 'unMarkExpired'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/mark-as-out-of-stock', [ProductController::class, 'markOutOfStock'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('{id}/un-mark-as-out-of-stock', [ProductController::class, 'unMarkOutOfStock'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::post('/', [ProductController::class, 'create'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::patch('{id}', [ProductController::class, 'update'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        Route::get('report', [ProductController::class, 'getReport'])->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST');
        // Route::delete('{id}', [ProductController::class, 'delete']);
    });

    Route::prefix('product-sales')->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST')->group(function () {
        Route::get('/', [ProductSalesController::class, 'findAll']);
        Route::get('{id}', [ProductSalesController::class, 'findOne']);
        Route::get('{id}/download-receipt', [ProductSalesController::class, 'downloadReceipt']);
        Route::post('/', [ProductSalesController::class, 'create']);
        // Route::patch('{id}', [ProductSalesController::class, 'update']);
        // Route::delete('{id}', [ProductSalesController::class, 'delete']);
        Route::get('report', [ProductSalesController::class, 'report']);
    });

    Route::prefix('product-manufacturers')->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST')->group(function () {
        Route::get('/', [ProductManufacturerController::class, 'findAll']);
        Route::get('/{id}', [ProductManufacturerController::class, 'findOne']);
        Route::post('/', [ProductManufacturerController::class, 'create']);
        Route::patch('{id}', [ProductManufacturerController::class, 'update']);
        // Route::delete('{id}', [ProductManufacturerController::class, 'delete']);
    });

    Route::prefix('product-types')->middleware('role:SUPER-ADMIN,ADMIN,PHARMACIST')->group(function () {
        Route::get('/', [ProductTypeController::class, 'findAll']);
        Route::get('/{id}', [ProductTypeController::class, 'findOne']);
        Route::post('/', [ProductTypeController::class, 'create']);
        Route::patch('{id}', [ProductTypeController::class, 'update']);
        // Route::delete('{id}', [ProductTypeController::class, 'delete']);
    });

    Route::prefix('general-examinations')->middleware('role:SUPER-ADMIN,NURSE,DOCTOR')->group(function () {
        Route::get('/', [GeneralExaminationController::class, 'findAll']);
        Route::get('/{id}', [GeneralExaminationController::class, 'findOne']);
        Route::post('/', [GeneralExaminationController::class, 'create']);
        Route::patch('{id}', [GeneralExaminationController::class, 'update']);
        // Route::delete('{id}', [GeneralExaminationController::class, 'delete']);
    });

    Route::prefix('physical-examinations')->middleware('role:SUPER-ADMIN,NURSE,DOCTOR')->group(function () {
        Route::get('/', [PhysicalExaminationController::class, 'findAll']);
        Route::get('{id}', [PhysicalExaminationController::class, 'findOne']);
        Route::post('/', [PhysicalExaminationController::class, 'create']);
        Route::patch('{id}', [PhysicalExaminationController::class, 'update']);
        // Route::delete('{id}', [PhysicalExaminationController::class, 'delete']);
    });

    Route::prefix('organisations')->group(function () {
        Route::get('{id}/overview', [OrganisationAndHmoController::class, 'getOverview']);
        Route::get('{id}/transactions', [OrganisationAndHmoController::class, 'getTransactions']);
        Route::get('{id}/payments', [OrganisationAndHmoController::class, 'getPayments']);
        Route::get('/', [OrganisationAndHmoController::class, 'findAll'])->middleware('role:ADMIN,SUPER-ADMIN,CASHIER,ACCOUNT');
        Route::get('{id}', [OrganisationAndHmoController::class, 'findOne'])->middleware('role:ADMIN,SUPER-ADMIN,CASHIER,ACCOUNT');
        Route::post('/', [OrganisationAndHmoController::class, 'create'])->middleware('role:ADMIN,SUPER-ADMIN');
        Route::patch('{id}', [OrganisationAndHmoController::class, 'update'])->middleware('role:ADMIN,SUPER-ADMIN');
        // Route::delete('{id}', [OrganisationAndHmoController::class, 'delete']);
    });

    Route::prefix('payments')->middleware('role:ADMIN,SUPER-ADMIN,CASHIER')->group(function () {
        Route::get('/', [PaymentController::class, 'findAll']);
        Route::get('generate-report', [PaymentController::class, 'exportTransactions']);
        Route::post('{id}/hmo', [PaymentController::class, 'addHMOPayment']);
        Route::patch('{id}/hmo', [PaymentController::class, 'updateHMOPayment']);
        Route::patch('{id}/update-amount-payable', [PaymentController::class, 'updateAmount']);
        Route::get('stats', [PaymentController::class, 'getStatsReport']);
        Route::get('monthly-stats', [PaymentController::class, 'getMonthlyReport']);
        Route::get('hmo-history', [PaymentController::class, 'findAllOrganisationPayments']);
        Route::get('{id}', [PaymentController::class, 'findOne']);
        Route::get('{id}/download-receipt', [PaymentController::class, 'downloadReceipt']);
        Route::patch('{id}/confirm', [PaymentController::class, 'markAsPaid']);
        Route::get('{id}/unconfirm', [PaymentController::class, 'markAsUnPaid']);
        Route::post('/', [PaymentController::class, 'create']);
        Route::patch('{id}', [PaymentController::class, 'update']);
        // Route::delete('{id}', [PaymentController::class, 'delete']);
        // Route::delete('/hmo/{id}', [PaymentController::class, 'deleteHmoPayment']);
    });

    Route::prefix('admissions')->middleware("role:SUPER-ADMIN,NURSE,DOCTOR")->middleware("role:ADMIN,SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [AdmissionController::class, 'findAll']);
        Route::get('{id}', [AdmissionController::class, 'findOne']);
        Route::get('{id}/discharge', [AdmissionController::class, 'discharge']);
        Route::get('{id}/re-admit', [AdmissionController::class, 'readmit']);
        Route::get('{id}/patient/add-to-debtors', [AdmissionController::class, 'addToDebtorList']);
        Route::get('{id}/patient/remove-from-debtors', [AdmissionController::class, 'removefromDebtorList']);
        Route::post('/', [AdmissionController::class, 'create']);
        Route::post('{id}/add-investigation', [AdmissionController::class, 'createAdmissionInvestigationNote']);
        Route::post('{id}/update-investigation', [AdmissionController::class, 'updateAdmissionNote']);
        Route::patch('{id}', [AdmissionController::class, 'update']);
        // Route::delete('{id}', [AdmissionController::class, 'delete']);
        Route::post('{id}/nurse-report', [AdmissionController::class, 'addNurseReport']);
        Route::patch('{id}/nurse-report', [AdmissionController::class, 'updateNurseReport']);
        Route::post('{id}/doctor-report', [AdmissionController::class, 'addDoctorReport']);
        Route::patch('{id}/doctor-report', [AdmissionController::class, 'updateDoctorReport']);
        Route::post('{id}/fluid-balance-chart', [AdmissionController::class, 'addFluidBalanceChart']);
        Route::patch('{id}/fluid-balance-chart', [AdmissionController::class, 'updateFluidBalanceChart']);
        // Route::delete('{id}/fluid-balance-chart', [AdmissionController::class, 'deleteFluidBalanceChart']);
        Route::post('{id}/drug-administration-chart', [AdmissionController::class, 'addDrugAdministrationChart']);
        Route::patch('{id}/drug-administration-chart', [AdmissionController::class, 'updateDrugAdministrationChart']);
        // Route::delete('{id}/drug-administration-chart', [AdmissionController::class, 'deleteDrugAdministrationChart']);
    });

    Route::prefix('beds')->middleware("role:ADMIN,SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [BedController::class, 'findAll']);
        Route::get('/dashboard', [BedController::class, 'dashboardReport']);
        Route::get('/available', [BedController::class, 'findAllAvailableBeds']);
        Route::get('{id}', [BedController::class, 'findOne']);
        Route::post('/', [BedController::class, 'create']);
        Route::patch('{id}', [BedController::class, 'update'])->middleware('role:ADMIN,SUPER-ADMIN');
        // Route::delete('{id}', [BedController::class, 'delete']);
    });

    Route::prefix('branches')->middleware("role:SUPER-ADMIN,ADMIN")->group(function () {
        Route::get('/', [BranchController::class, 'findAll']);
        Route::get('/search', [BranchController::class, 'findAllWithoutPagination']);
        Route::get('{id}', [BranchController::class, 'findOne']);
        Route::post('/', [BranchController::class, 'create']);
        Route::patch('{id}', [BranchController::class, 'update']);
        // Route::delete('{id}', [BranchController::class, 'delete']);
    });

    Route::prefix('rooms')->middleware("role:SUPER-ADMIN,ADMIN")->group(function () {
        Route::get('/', [RoomController::class, 'findAllPagination']);
        Route::get('/search', [RoomController::class, 'findAllWithoutPagination']);
        Route::get('{id}', [RoomController::class, 'findOne']);
        Route::post('/', [RoomController::class, 'create']);
        Route::patch('{id}', [RoomController::class, 'update']);
        // Route::delete('{id}', [RoomController::class, 'delete']);
        Route::get('{id}/{status}', [RoomController::class, 'toggleStatus']);
    });

    Route::prefix('room-categories')->middleware("role:SUPER-ADMIN,ADMIN")->group(function () {
        Route::get('/', [RoomCategoryController::class, 'findAllWithPagination']);
        Route::get('/search', [RoomCategoryController::class, 'findAll']);
        Route::get('{id}', [RoomCategoryController::class, 'findOne']);
        Route::post('/', [RoomCategoryController::class, 'create']);
        Route::patch('{id}', [RoomCategoryController::class, 'update']);
        // Route::delete('{id}', [RoomCategoryController::class, 'delete']);
    });

    Route::prefix('vital-signs')->middleware("role:SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [VitalSignController::class, 'findAll']);
        Route::get('{id}', [VitalSignController::class, 'findOne']);
        Route::post('/', [VitalSignController::class, 'create']);
        Route::patch('{id}', [VitalSignController::class, 'update']);
        // Route::delete('{id}', [VitalSignController::class, 'delete']);
    });

    Route::prefix('notes')->group(function () {
        // Route::get('/', [NoteController::class, 'findAll']);
        // Route::get('{id}', [NoteController::class, 'findOne']);
        // Route::post('/', [NoteController::class, 'create']);
        Route::patch('{id}', [NoteController::class, 'update']);
        // Route::delete('{id}', [NoteController::class, 'delete']);
    });

    Route::prefix('ante-natals')->middleware("role:SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [AnteNatalController::class, 'findAll']);
        Route::get('{id}', [AnteNatalController::class, 'findOne']);
        Route::post('/', [AnteNatalController::class, 'createAccount']);
        Route::post('/{id}/add-scan-report', [AnteNatalController::class, 'addScanReport']);
        Route::patch('update-status/{id}', [AnteNatalController::class, 'updateStatus']);
        Route::post('add-routine-assessment/{id}', [AnteNatalController::class, 'addRoutineAssessment']);
        Route::patch('update-routine-assessment/{id}', [AnteNatalController::class, 'editRoutineAssessment']);
        Route::patch('{id}', [AnteNatalController::class, 'update']);
        // Route::delete('{id}', [AnteNatalController::class, 'delete']);
    });

    // Route::prefix('branches')->group(function () {
    //     Route::get('/', [BranchController::class, 'findAll']);
    //     Route::get('{id}', [BranchController::class, 'findOne']);
    //     Route::post('/', [BranchController::class, 'create']);
    //     Route::patch('{id}', [BranchController::class, 'update']);
    //     // Route::delete('{id}', [BranchController::class, 'destroy']);
    // });

    Route::prefix('product-purchases')->group(function () {
        Route::get('/', [ProductPurchaseController::class, 'findAll']);
        Route::get('{id}', [ProductPurchaseController::class, 'findOne']);
        Route::get('{id}/approve', [ProductPurchaseController::class, 'approve']);
        Route::get('{id}/disapprove', [ProductPurchaseController::class, 'disapprove']);
        Route::post('/', [ProductPurchaseController::class, 'create']);
        Route::patch('{id}', [ProductPurchaseController::class, 'update']);
        // Route::delete('{id}', [ProductPurchaseController::class, 'delete']);
    });

    Route::prefix('operation-records')->middleware("role:SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [OperationRecordController::class, 'findAll']);
        Route::get('{id}', [OperationRecordController::class, 'findOne']);
        Route::post('/', [OperationRecordController::class, 'create']);
        Route::patch('{id}', [OperationRecordController::class, 'update']);
        // Route::delete('{id}', [OperationRecordController::class, 'remove']);
    });

    Route::prefix('labour-records')->middleware("role:SUPER-ADMIN,NURSE,DOCTOR")->group(function () {
        Route::get('/', [LabourRecordController::class, 'findAll']);
        Route::get('{id}', [LabourRecordController::class, 'findOne']);
        Route::post('/', [LabourRecordController::class, 'create']);
        Route::patch('{id}', [LabourRecordController::class, 'update'])->middleware("role:SUPER-ADMIN,DOCTOR");
        Route::patch('{id}/update-graph-progression', [LabourRecordController::class, 'updateProgression']);
        Route::post('{id}/add-graph-progression', [LabourRecordController::class, 'createProgression']);
        Route::patch('{id}/update-summary', [LabourRecordController::class, 'updateSummary']);
        Route::post('{id}/add-summary', [LabourRecordController::class, 'createSummary']);
        // Route::delete('{id}/delete-graph-progression', [LabourRecordController::class, 'deleteProgression']);
        // Route::delete('{id}', [LabourRecordController::class, 'remove']);
    });

    Route::prefix('prescriptions')->group(function () {
        Route::get('/search', [PrescriptionController::class, 'searchProductByName']);
        Route::get('/', [PrescriptionController::class, 'findAll']);
        Route::get('{id}', [PrescriptionController::class, 'findOne']);
        Route::post('{id}/add-notes', [PrescriptionController::class, 'addNote'])->middleware("role:SUPER-ADMIN,DOCTOR,NURSE");
        Route::post('/', [PrescriptionController::class, 'create'])->middleware("role:SUPER-ADMIN,DOCTOR");
        Route::patch('{id}', [PrescriptionController::class, 'update'])->middleware("role:SUPER-ADMIN,DOCTOR");
        // Route::delete('{id}', [PrescriptionController::class, 'delete']);
        Route::put('{id}/item', [PrescriptionController::class, 'updatePrescriptionItem'])->middleware("role:SUPER-ADMIN,DOCTOR");
        Route::post('{id}/dispense-items', [PrescriptionController::class, 'markItemsAsDispensed'])->middleware("role:PHARMACIST,SUPER-ADMIN");
        Route::post('{id}/items-not-available', [PrescriptionController::class, 'markItemsNotAvailable'])->middleware("role:PHARMACIST,PHARMACIST,SUPER-ADMIN");;
        Route::post('{id}/remove-items', [PrescriptionController::class, 'removeItems'])->middleware("role:SUPER-ADMIN,DOCTOR");
        Route::post('{id}/add-items', [PrescriptionController::class, 'addMoreItems'])->middleware("role:SUPER-ADMIN,DOCTOR");
        Route::get('{id}/item/{status}', [PrescriptionController::class, 'updatePrescriptionItemStatus'])->middleware("role:SUPER-ADMIN,PHARMACIST");;
    });

    Route::prefix('services')->group(function () {
        Route::get('/search', [ServiceController::class, 'searchProductByName']);
        Route::get('/available-tests', [ServiceController::class, 'getAllTests']);
        Route::get('/available-tests/search', [ServiceController::class, 'searchTests']);
        Route::get('/lab-tests', [ServiceController::class, 'getAllLabTests']);
        Route::get('/radiology-tests', [ServiceController::class, 'getAllRadiologyTests']);
        Route::get('/', [ServiceController::class, 'findAll']);
        Route::get('{id}', [ServiceController::class, 'findOne']);
        Route::post('/', [ServiceController::class, 'store'])->middleware("role:SUPER-ADMIN,ADMIN");
        Route::patch('{id}', [ServiceController::class, 'update']);
        // Route::delete('{id}', [ServiceController::class, 'delete'])
    });

    Route::prefix('service-categories')->middleware("role:SUPER-ADMIN,ADMIN")->group(function () {
        Route::get('/', [ServiceCategoryController::class, 'findAll']);
        Route::get('/search', [ServiceCategoryController::class, 'getAllWithoutPagination']);
        Route::get('/', [ServiceCategoryController::class, 'findAll']);
        Route::get('{id}', [ServiceCategoryController::class, 'findOne']);
        Route::post('/', [ServiceCategoryController::class, 'store']);
        Route::patch('{id}', [ServiceCategoryController::class, 'update']);
        // Route::delete('{id}', [ServiceCategoryController::class, 'delete']);
    });

    Route::prefix('treatments')->middleware("role:SUPER-ADMIN,DOCTOR,NURSE")->group(function () {
        Route::get('/', [TreatmentController::class, 'findAll']);
        Route::get('{id}', [TreatmentController::class, 'findOne']);
        // Route::get('{id}/accept', [TreatmentController::class, 'accept'])->middleware("role:SUPER-ADMIN,DOCTOR,NURSE");
        Route::get('{id}/canceled', [TreatmentController::class, 'cancel']);
        Route::get('{id}/in-progress', [TreatmentController::class, 'inProgress']);
        Route::get('{id}/completed', [TreatmentController::class, 'complete']);
        Route::post('/', [TreatmentController::class, 'create'])->middleware("role:SUPER-ADMIN,DOCTOR");;
        // Route::patch('{id}/reschedule', [TreatmentController::class, 'reschedule'])->middleware("role:SUPER-ADMIN,NURSE");
        Route::patch('{id}', [TreatmentController::class, 'update'])->middleware("role:SUPER-ADMIN,DOCTOR");
        // Route::delete('{id}', [TreatmentController::class, 'delete']);
        Route::post('{id}/add-items', [TreatmentController::class, 'addItems']);
        Route::post('{id}/add-note', [TreatmentController::class, 'addNote']);
        Route::patch('{id}/update-items', [TreatmentController::class, 'addItems']);
    });

    Route::prefix('test-requests')->group(function () {
        Route::post('/lab-request', [LabRequestController::class, 'storeLabRequest'])->middleware("role:SUPER-ADMIN,LAB-TECHNOLOGIST");
        Route::post('/radiology-request', [LabRequestController::class, 'storeRadiologyRequest'])->middleware("role:SUPER-ADMIN,RADIOLOGIST");
        Route::post('{id}/add-results', [LabRequestController::class, 'createResult'])->middleware("role:SUPER-ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");
        Route::get('dashboard', [LabRequestController::class, 'getReport']);
        Route::patch('{id}/update-result', [LabRequestController::class, 'updateResult'])->middleware("role:SUPER-ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");
        // Route::get('/lab-tests', [LabRequestController::class, 'findAllLabRequests']);
        Route::get('/radiology-tests', [LabRequestController::class, 'findAllRadiologyRequests']);
        Route::get('/radiology-requests', [LabRequestController::class, 'findAllRadiologyRequests'])->middleware("role:SUPER-ADMIN,RADIOLOGIST");
        Route::get('/lab-requests', [LabRequestController::class, 'findAllLabRequests']);
        Route::get('{id}', [LabRequestController::class, 'findOne']);
        Route::patch('{id}/lab-request', [LabRequestController::class, 'update'])->middleware("role:SUPER-ADMIN,LAB-TECHNOLOGIST");
        Route::patch('{id}/radiology-request', [LabRequestController::class, 'updateRadiology'])->middleware("role:SUPER-ADMIN,RADIOLOGIST");
        // Route::delete('{id}', [LabRequestController::class, 'destroy']);
    });

    Route::prefix('stock-reports')->group(function () {
        Route::post('/', [StockReportController::class, 'create'])->middleware("role:SUPER-ADMIN,PHARMACIST");
        Route::get('/', [StockReportController::class, 'findAll'])->middleware("role:SUPER-ADMIN,PHARMACIST");
        Route::get('{id}', [StockReportController::class, 'findOne'])->middleware("role:SUPER-ADMIN,PHARMACIST");
        Route::patch('{id}', [StockReportController::class, 'update'])->middleware("role:SUPER-ADMIN,PHARMACIST");
        // Route::delete('{id}', [StockReportController::class, 'delete']);
    });

    Route::prefix('test-result-templates')->group(function () {
        Route::post('/', [LabTestResultTemplateController::class, 'create'])->middleware("role:SUPER-ADMIN,ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");;
        Route::get('/', [LabTestResultTemplateController::class, 'findAll'])->middleware("role:SUPER-ADMIN,ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");;
        Route::get('{id}', [LabTestResultTemplateController::class, 'findOne'])->middleware("role:SUPER-ADMIN,ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");;
        Route::patch('{id}', [LabTestResultTemplateController::class, 'update'])->middleware("role:SUPER-ADMIN,ADMIN,LAB-TECHNOLOGIST,RADIOLOGIST");;
        // Route::delete('{id}', [LabTestResultTemplateController::class, 'delete']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'findAll']);
        Route::get('/doctors', [UserController::class, 'findDoctors']);
        Route::get('/all-doctors', [UserController::class, 'findDoctorsWithoutPagination']);
        Route::get('/nurses', [UserController::class, 'findNurses']);
        Route::get('{id}', [UserController::class, 'findOne']);
        Route::get('{id}/change-status/{status}', [UserController::class, 'toggleStatus']);
        Route::get('/payments', [UserController::class, 'findUserControllerPayments'])->middleware("role:SUPER-ADMIN,ADMIN,RECORD-KEEPER");;
        Route::post('/', [UserController::class, 'create'])->middleware("role:SUPER-ADMIN,ADMIN,RECORD-KEEPER");
        Route::post('/upload', [UserController::class, 'upload']);
        Route::patch('{id}', [UserController::class, 'update']);
        // Route::delete('{id}', [UserController::class, 'delete']);
    });
});
