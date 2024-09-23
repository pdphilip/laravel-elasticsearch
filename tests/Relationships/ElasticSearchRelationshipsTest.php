<?php

  declare(strict_types=1);

  use Workbench\App\Models\Avatar;
  use Workbench\App\Models\Company;
  use Workbench\App\Models\CompanyLog;
  use Workbench\App\Models\CompanyProfile;
  use Workbench\App\Models\Photo;

  beforeEach(function () {
    Company::truncate();
    CompanyLog::truncate();
    CompanyProfile::truncate();
    Avatar::truncate();
    Photo::truncate();

  });

  it('should show company relationships', function () {
    $logsPerCompany = 10;
    $photosPerCompany = 5;

    createCompanyData(
      photosPerCompany: $photosPerCompany,
      logsPerCompany: $logsPerCompany,
    );

    $company = Company::first();

    expect($company->companyLogs)->not()->toBeEmpty()
                                 ->and(count($company->companyLogs))->toBe($logsPerCompany)
                                 ->and($company->companyProfile->_id)->not()->toBeEmpty()
                                 ->and($company->avatar->_id)->not()->toBeEmpty()
                                 ->and($company->photos)->not()->toBeEmpty()
                                 ->and(count($company->photos))->toBe($photosPerCompany);


  });

  it('should show user log (ES) relationship to user', function () {
    createCompanyData();

    $companyLog = CompanyLog::first();
    expect($companyLog->company->_id)->not()->toBeEmpty()
                                     ->and($companyLog->company->companyProfile->_id)->not()->toBeEmpty();

  });

  it('should show 1 to 1 ES relationships for user and company', function () {
    createCompanyData();
    $companyProfile = CompanyProfile::first();
    expect($companyProfile->company->_id)->not()->toBeEmpty();

  });
