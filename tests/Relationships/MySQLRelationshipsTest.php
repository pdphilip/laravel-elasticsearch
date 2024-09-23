<?php

  declare(strict_types=1);

  use Workbench\App\Models\Avatar;
  use Workbench\App\Models\Company;
  use Workbench\App\Models\CompanyProfile;
  use Workbench\App\Models\Photo;
  use Workbench\App\Models\User;
  use Workbench\App\Models\UserLog;
  use Workbench\App\Models\UserProfile;

  beforeEach(function () {
    User::truncate();
    Company::truncate();
    UserLog::truncate();
    UserProfile::truncate();
    CompanyProfile::truncate();
    Avatar::truncate();
    Photo::truncate();
  });

  it('should show user relationships to ES models', function () {

    $usersPerCompany = 3;
    $logsPerUser = 10;
    $photosPerUser = 5;
    $photosPerCompany = 2;

    createCompanyData(
      photosPerCompany: $photosPerCompany,
      usersPerCompany : $usersPerCompany,
      photosPerUser   : $photosPerUser,
      logsPerUser   : $logsPerUser
    );
    $user = User::first();

    expect($user->company->_id)->not()->toBeEmpty()
                               ->and($user->company->avatar->_id)->not()->toBeEmpty()
                               ->and($user->photos)->not()->toBeEmpty()
                               ->and(count($user->photos))->toBe($photosPerUser)
                               ->and($user->userLogs)->not()->toBeEmpty()
                               ->and(count($user->userLogs))->toBe($logsPerUser)
                               ->and($user->userProfile->_id)->not()->toBeEmpty()
                               ->and($user->avatar->_id)->not()->toBeEmpty();

  });


  it('should show user log (ES) relationship to user', function () {
    $usersPerCompany = 3;
    $logsPerUser = 10;
    $photosPerUser = 5;
    $photosPerCompany = 2;

    createCompanyData(
      photosPerCompany: $photosPerCompany,
      usersPerCompany : $usersPerCompany,
      photosPerUser   : $photosPerUser,
      logsPerUser   : $logsPerUser
    );

    $userLog = UserLog::first();

    expect($userLog->user->id)->not()->toBeEmpty()
        ->and($userLog->user->company->_id)->not()->toBeEmpty()
        ->and($userLog->user->userProfile->_id)->not()->toBeEmpty()
        ->and($userLog->company->_id)->not()->toBeEmpty()
         ->and($userLog->company->users)->toHaveCount($usersPerCompany)
         ->and($userLog->company->companyProfile->_id)->not()->toBeEmpty();


  });

  it('should show 1 to 1 ES relationships for user and company', function () {
    $usersPerCompany = 3;
    $logsPerUser = 10;
    $photosPerUser = 5;
    $photosPerCompany = 2;

    createCompanyData(
      photosPerCompany: $photosPerCompany,
      usersPerCompany : $usersPerCompany,
      photosPerUser   : $photosPerUser,
      logsPerUser   : $logsPerUser
    );

    $companyProfile = CompanyProfile::first();
    $userProfile = UserProfile::first();

    expect($companyProfile->company->_id)->not()->toBeEmpty()
       ->and($userProfile->user->id)->not()->toBeEmpty();

  });
