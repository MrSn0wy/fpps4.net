use std::{env, fs};
use std::path::Path;
use crate::macros::PolarResultUnwrap;
use crate::panic_red;

pub fn get_cli_variables() -> (String, String, String, String) {
    let args : Vec<String>=  env::args().collect();
    
    let mut github_token: &str = "";
    let mut homebrew_token: &str = "";
    let mut database_path: &str = "";
    let mut images_path: &str = "";

    for (n, string) in args.iter().enumerate() {

        // dbg!(string.to_lowercase().as_str());

        match string.to_lowercase().as_str() {
            "--github_token" => {
                github_token = match args.get(n+1) {
                    None => "",
                    Some(string) => {
                        if !string.starts_with("--") {
                            string
                        } else { 
                            ""
                        }
                    },
                };
            }
            "--homebrew_token" => {
                homebrew_token = match args.get(n+1) {
                    None => "",
                    Some(string) => {
                        if !string.starts_with("--") {
                            string
                        } else {
                            ""
                        }
                    },
                };
            }
            "--database_path" => {
                database_path = match args.get(n+1) {
                    None => "",
                    Some(string) => {
                        if !string.starts_with("--") {
                            string
                        } else {
                            ""
                        }
                    },
                };
            }
            "--images_path" => {
                images_path = match args.get(n+1) {
                    None => "",
                    Some(string) => {
                        if !string.starts_with("--") {
                            string
                        } else {
                            ""
                        }
                    },
                };
            }
            _ => {},
        }

    }

    // dbg!(homebrew_token);
    // dbg!(github_token);
    // dbg!(database_path);
    // dbg!(images_path);

    if github_token.is_empty() || homebrew_token.is_empty() || database_path.is_empty() || images_path.is_empty() {
        panic_red!("Missing CLI arguments!")
    }

    (github_token.to_string(), homebrew_token.to_string(), database_path.to_string(), images_path.to_string())
}


pub fn setup_dirs(database_path: &str, images_path: &str) {

    if !Path::new(database_path).exists() {
        fs::create_dir_all(database_path).polar_unwrap("Error creating database_path!", true);
    }

    if !Path::new(images_path).exists() {
        fs::create_dir_all(images_path).polar_unwrap("Error creating images_path!", true);
    }


    let game_images_path = format!("{images_path}/game");
    let homebrew_images_path = format!("{images_path}/homebrew");

    if !Path::new(&game_images_path).exists() {
        fs::create_dir(game_images_path).polar_unwrap("Error creating game_images_path!", true);
    }

    if !Path::new(&homebrew_images_path).exists() {
        fs::create_dir(homebrew_images_path).polar_unwrap("Error creating homebrew_images_path!", true);
    }
}