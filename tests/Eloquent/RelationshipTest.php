<?php

declare(strict_types=1);

use Workbench\App\Models\Avatar;
use Workbench\App\Models\Company;
use Workbench\App\Models\CompanyLog;
use Workbench\App\Models\CompanyProfile;
use Workbench\App\Models\Photo;

test('company has many company logs', function () {
    $company = Company::factory()->create();
    $logs = CompanyLog::factory(3)->create(['company_id' => $company->id]);
    $fetchedLogs = $company->companyLogs;

    expect($fetchedLogs)->toHaveCount(3)
        ->and($fetchedLogs->first())->toBeInstanceOf(CompanyLog::class);
});

test('company has one company profile', function () {
    $company = Company::factory()->create();
    $profile = CompanyProfile::factory()->create(['company_id' => $company->id]);
    $fetchedProfile = $company->companyProfile;

    expect($fetchedProfile)->toBeInstanceOf(CompanyProfile::class)
        ->and($fetchedProfile->id)->toEqual($profile->id);
});

test('company has one avatar using morphOne', function () {
    $company = Company::factory()->create();
    $avatar = Avatar::factory()->create(['imageable_id' => $company->id, 'imageable_type' => Company::class]);
    $fetchedAvatar = $company->avatar;

    expect($fetchedAvatar)->toBeInstanceOf(Avatar::class)
        ->and($fetchedAvatar->id)->toEqual($avatar->id);
});

test('company has many photos using morphMany', function () {
    $company = Company::factory()->create();
    $photos = Photo::factory(5)->create(['photoable_id' => $company->id, 'photoable_type' => Company::class]);
    $fetchedPhotos = $company->photos;

    expect($fetchedPhotos)->toHaveCount(5)
        ->and($fetchedPhotos->first())->toBeInstanceOf(Photo::class);
});
