<?php

use Workbench\App\Models\Avatar;
use Workbench\App\Models\Company;
use Workbench\App\Models\CompanyLog;
use Workbench\App\Models\Photo;
use Workbench\App\Models\User;
use Workbench\App\Models\UserLog;
use Workbench\App\Models\UserProfile;
use Workbench\Database\Factories\CompanyFactory;
use Workbench\Database\Factories\CompanyProfileFactory;

function createCompanyData($companies = 1, $photosPerCompany = 5, $logsPerCompany = 10, $usersPerCompany = 2, $photosPerUser = 5, $logsPerUser = 5): void
{
    $i = 0;
    while ($i < $companies) {
        // Build the data for this test
        $cf = new CompanyFactory;
        $company = $cf->makeOne();
        $company->save();
        $companyId = $company->id;

        $avatar = new Avatar;
        $avatar->url = $company->name.'_pic.jpg';
        $avatar->imageable_id = $companyId;
        $avatar->imageable_type = Company::class;
        $avatar->save();

        // We can collect and bulk insert to save time.
        $photos = collect();

        $photoLog = Photo::factory($photosPerCompany)->state([
            'photoable_id' => $companyId,
            'photoable_type' => Company::class,
        ])->make();
        $photos->push(...$photoLog->toArray());

        $cpf = new CompanyProfileFactory;
        $companyProfile = $cpf->makeOne();
        $companyProfile->company_id = $companyId;
        $companyProfile->save();

        $companyLog = CompanyLog::factory($logsPerCompany)->state([
            'company_id' => $companyId,
        ])->make();
        CompanyLog::insert($companyLog->toArray());

        // Add user info for testing
        $users = User::factory($usersPerCompany)->state([
            'company_id' => $companyId,
        ])->create();

        $userProfiles = collect();
        $userAvatars = collect();
        $userLogs = collect();

        foreach ($users as $user) {
            // Make user Profiles here
            $userProfile = UserProfile::factory()->state([
                'user_id' => $user->id,
            ])->makeOne();
            $userProfiles->push($userProfile->toArray());

            // Make Avatar for users.
            $userAvatar = Avatar::factory()->state([
                'imageable_id' => $user->id,
                'imageable_type' => User::class,
            ])->makeOne();
            $userAvatars->push($userAvatar->toArray());

            $userlog = UserLog::factory($logsPerUser)->state([
                'company_id' => $companyId,
                'user_id' => $user->id,
            ])->make();

            $userLogs->push(...$userlog->toArray());

            $userPhotos = Photo::factory($photosPerUser)->state([
                'photoable_id' => $user->id,
                'photoable_type' => User::class,
            ])->make();

            $photos->push(...$userPhotos->toArray());
        }

        UserLog::insert($userLogs->toArray());
        UserProfile::insert($userProfiles->toArray());
        Avatar::insert($userAvatars->toArray());
        Photo::insert($photos->toArray());

        $i++;

    }
}
