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
        ...
      }: let
        serv = config.snow-blower.services;
        lang = config.snow-blower.languages;

        # Refrences PHP and Composer later in this config.
        composer = "${lang.php.packages.composer}/bin/composer";
        php = "${lang.php.package}/bin/php";

        envKeys = builtins.attrNames config.snow-blower.env;
        unsetEnv = builtins.concatStringsSep "\n" (
          map (key: "unset ${key}") envKeys
        );
      in {
        snow-blower = {
          paths.src = ./.;

          # Conviance scripts
          scripts = {
            pf.exec = ''
              ${unsetEnv}
              ./vendor/bin/pest --filter "$@"
            '';
            p.exec = ''
              ${unsetEnv}
              ./vendor/bin/pest
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
              version = "8.2";
              extensions = ["grpc" "redis" "imagick" "memcached" "xdebug"];
              ini = ''
                memory_limit = 5G
                max_execution_time = 90
              '';
            };
          };

          services = {
            # Elasticsearch service for testing
            elasticsearch = {
              enable = true;
            };
          };

          integrations = {
            #Creates Changelogs based on commits
            git-cliff.enable = true;

            treefmt = {
              programs = {
                #Nix Formater
                alejandra.enable = true;

                #Format Markdown files.
                mdformat.enable = true;

                #PHP CS Fixer setup with Laravel Pint Standerds
                php-cs-fixer.enable = false;

                #JS / CSS Formatting.
                prettier = {
                  enable = true;
                  settings = {
                    trailingComma = "es5";
                    semi = true;
                    singleQuote = true;
                    jsxSingleQuote = true;
                    bracketSpacing = true;
                    printWidth = 80;
                    tabWidth = 2;
                    endOfLine = "lf";
                  };
                };
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
                  ${composer} install
              fi
            ''
          ];
        };
      };
    };
}
