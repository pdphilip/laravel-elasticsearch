{
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-parts.url = "github:hercules-ci/flake-parts";
    snow-blower.url = "github:use-the-fork/snow-blower";
  };

  outputs = inputs:
    inputs.snow-blower.mkSnowBlower {
      inherit inputs;
      perSystem = {
        config,
        lib,
        pkgs,
        ...
      }: let
        rootDir = config.snow-blower.paths.root;
        serv = config.snow-blower.services;
        lang = config.snow-blower.languages;

        envKeys = builtins.attrNames config.snow-blower.env;
        unsetEnv = builtins.concatStringsSep "\n" (
          map (key: "unset ${key}") envKeys
        );
      in {
        snow-blower = {
          paths.src = ./.;

          packages = [
            # Local github runner
            pkgs.act
          ];

          # Convince scripts
          scripts = {
            pf.exec = ''
              ${unsetEnv}
              ./vendor/bin/pest --filter "$@"
            '';
            p.exec = ''
              ${unsetEnv}
              ./vendor/bin/pest
            '';
            coverage.exec = ''
               export XDEBUG_MODE=coverage
              ./vendor/bin/pest --coverage
              unset XDEBUG_MODE
            '';
            coverage-xml.exec = ''
               export XDEBUG_MODE=coverage
              ./vendor/bin/pest --coverage --coverage-clover clover.xml
              unset XDEBUG_MODE
            '';

            # swap a and artisan commands for testbench
            a.exec = ''
              ${unsetEnv}
              ./vendor/bin/testbench "$@"
            '';
            artisan.exec = ''
              ${unsetEnv}
              ./vendor/bin/testbench "$@"
            '';
          };

          languages = {
            # the required version of PHP for this project.
            php = {
              enable = true;
              package = pkgs.php82;
              extensions = ["grpc" "redis" "imagick" "memcached" "xdebug"];
              ini = ''
                memory_limit = 5G
                max_execution_time = 90

                [xdebug]
                xdebug.start_with_request=yes
                xdebug.start_upon_error=yes
              '';
            };
          };

          services = {
            aider.enable = true;
            # Elasticsearch service for testing
            elasticsearch = {
              enable = true;
            };
          };

          integrations = {
            #Creates Changelogs based on commits
            git-cliff.enable = true;

            treefmt = {
              settings.formatter = {
                # Laravel Pint Formating
                "laravel-pint" = {
                  command = "php";
                  options = [
                    "${rootDir}/vendor/bin/pint"
                    #make it verbose
                    "-v"
                    "--repair"
                  ];
                  includes = ["*.php"];
                };
              };

              programs = {
                #Nix Formater
                alejandra.enable = true;

                #Format Markdown files.
                mdformat.enable = true;
              };
            };

            # Guess what this does. Go ahead Guess.
            git-hooks.hooks = {
              # run formatting on files that are being commited
              treefmt.enable = true;

              #lets make sure there are no keys in the repo
              detect-private-keys.enable = true;

              #fix line endings.
              mixed-line-endings.enable = true;
            };
          };

          shell.interactive = [
            ''
              if [[ ! -d vendor ]]; then
                  composer install
              fi
            ''
          ];
        };
      };
    };
}
