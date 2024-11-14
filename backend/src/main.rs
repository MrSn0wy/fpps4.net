use regex::Regex;
use std::{fs, thread};
use crate::macros::PolarOptionUnwrap;
use crate::parsing::Issue;
use crossbeam_queue::{ArrayQueue, SegQueue};
use macros::PolarResultUnwrap;
use std::sync::{Arc, Mutex};
use rusqlite::Connection;
use serde_json::Value;
use crate::networking::homebrew_database_updater;

mod networking;
mod config;
mod images;
mod parsing;
mod macros;

const HB_DATABASE: &str = "./HBstore.db";
const GAME_SKIPS_DATABASE: &str = "./game_skips.json";

fn main() {
    println!("Hello, world!");

    // get needed variables
    let code_regex = Arc::new( Regex::new(r"[a-zA-Z]{4}[0-9]{5}").polar_unwrap("[ERR]: unable to create regex!", true) );

    let (github_token, homebrew_token, database_path, images_path) = config::get_cli_variables();
    config::setup_dirs(&database_path, &images_path);

    // update the homebrew database and get a connection
    homebrew_database_updater(HB_DATABASE, &homebrew_token).polar_unwrap("", true);
    let homebrew_db = Connection::open(HB_DATABASE).polar_unwrap("Failed to open SQLite connection to homebrew database!", true);
    let homebrew_db = Arc::new( Mutex::new(homebrew_db) );

    let (page_count, issues_count): (u64, u64) = networking::github_get_repo_info(&github_token);


    println_cyan!("Total Issues: {}", issues_count);
    println_cyan!("Total Pages: {}", page_count);


    // setup multi-threading
    let issues: Arc< ArrayQueue<Issue> > = Arc::new(ArrayQueue::new(issues_count as usize));

    let old_game_skips: Arc< Vec<u64> > = {
        match fs::read_to_string(GAME_SKIPS_DATABASE) {
            Ok(string) => Arc::new(serde_json::from_str(&string).unwrap_or_default()),
            Err(_) => Arc::new(vec![])
        }
    };

    let game_skips: Arc< SegQueue<u64> > = {
        let queue = SegQueue::new();
        old_game_skips.iter().for_each( |x| queue.push(x.to_owned()));
        Arc::new(queue)
    };

    let mut handles = vec![];

    for page in 1..=page_count {
        let issues = issues.clone();
        let game_skips = game_skips.clone();
        let old_game_skips = old_game_skips.clone();
        let regex = code_regex.clone();
        let homebrew_db = homebrew_db.clone();
        let github_token = github_token.clone();
        let homebrew_token = homebrew_token.clone();
        let images_path = images_path.clone();


        let handle = thread::spawn( move || {

            let github_issues = networking::github_get_issues(page, &github_token);

            for issue in github_issues.iter() {
                let parsed_issue = parsing::parse_github_issue(issue, &regex);

                match parsed_issue {
                    Ok(mut issue) => {
                        // check if there are any warnings
                        if !issue.1.is_empty() {
                            println_cyan!("{} -> {:?}", issue.0.id, issue.1);
                        }

                        // Do image handling here! :D
                        if !old_game_skips.contains(&issue.0.id) {
                            if let Err(err) = images::get_converted_image_data(&mut issue.0, &images_path, homebrew_db.clone(), &homebrew_token) {
                                game_skips.push(issue.0.id);
                                eprintln_red!("Error downloading image for {} : {:?}", issue.0.id, err.root_cause());
                            };
                        }


                        issues.push(issue.0).polar_unwrap("Can't add issue to the Array!", true)
                    }
                    Err(err) => eprintln_red!("{} -> {} ", issue.get("number").and_then(Value::as_u64).unwrap_or_default(), err),
                }
            }
        });

        handles.push(handle);
    }


    // Wait for all threads to finish
    for handle in handles {
        handle.join().polar_unwrap("Error waiting on threads to finish!", true); // Join each thread and handle potential errors
    }

    // get issues and games_kips
    let issues = Arc::into_inner(issues).polar_unwrap("Error while retrieving issues value!");
    let mut issues: Vec<Issue> = issues.into_iter().collect();

    let game_skips = Arc::into_inner(game_skips).polar_unwrap("Error while retrieving issues value!");
    let mut game_skips: Vec<u64> = game_skips.into_iter().collect();


    // sort issues and game_skips
    issues.sort_by_key(|issue| !issue.id);
    game_skips.sort();


    // saves databases
    let temp_string = serde_json::to_string(&issues).polar_unwrap("error making issues into a json!", true);
    let temp_path = format!("{database_path}/database.json");
    fs::write(temp_path, temp_string).polar_unwrap("Error saving the \"database\" file!", true);

    let temp_string = serde_json::to_string(&game_skips).polar_unwrap("error making game_skips into a json!", true);
    fs::write(GAME_SKIPS_DATABASE, temp_string).polar_unwrap("Error saving the \"game_skips\" file!", true);

    println_green!("dun! {} issues processed", issues.len());
}