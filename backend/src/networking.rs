use std::fs;
use std::fs::File;
use anyhow::{bail, Context, Error};
use hmac::KeyInit;
use serde_json::Value;
use crate::{ panic_red, println_cyan};
use crate::macros::{PolarOptionUnwrap, PolarResultUnwrap};

const API_URL: &str = "https://api.github.com/repos/red-prig/fpps4-game-compatibility";
const TMDB_HEX: &str = "F5DE66D2680E255B2DF79E74F890EBF349262F618BCAE2A9ACCDEE5156CE8DF2CDF2D48C71173CDC2594465B87405D197CF1AED3B7E9671EEB56CA6753C2E6B0";
const PS4_USER_AGENT: &str = "Mozilla/5.0 (PlayStation; PlayStation 4/11.00) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Safari/605.1.15";


fn github_request(url: &str, token: &str) -> Value {
    match ureq::get(url)
        .set("User-Agent", "fpps4.net")
        .set("Accept", "application/vnd.github+json")
        .set("Authorization", &format!("Bearer {}", token))
        .call()
    {
        Ok(response) => response.into_json().polar_unwrap("Failed to parse JSON", true),
        Err(response) => panic_red!("Github request failed: {}", response),
    }
}


pub(crate) fn github_get_repo_info(token: &str) -> (u64, u64) {
    let total_issues: u64 = github_request(API_URL, &token)
        .get("open_issues_count")
        .and_then(Value::as_u64)
        .polar_unwrap("Failed to parse total_issues JSON!");

    let total_pages: u64 = total_issues.div_ceil(100);
    (total_pages, total_issues)
}

pub(crate) fn github_get_issues(page: u64, token: &str) -> Vec<Value> {
    let url = format!(
        "{}/issues?page={}&per_page=100&state=open&direction=DESC",
        API_URL, page
    );

    // need to clone otherwise it is a reference -.-
    github_request(url.as_str(), token)
        .as_array()
        .cloned()
        .polar_unwrap("Error getting issues!")
}

pub(crate) fn get_game_image_data(gamecode: &str) -> Result<Vec<u8>, Error> {
    use hmac::{Hmac, Mac};
    use sha1::Sha1;
    use hex::FromHex;
    
    // create the url based on the TMDB hash
    let mut hmac = Hmac::<Sha1>::new_from_slice(&Vec::from_hex(TMDB_HEX)?)?;
    let image_code = format!("{}_00", gamecode);
    hmac.update(image_code.as_bytes());
    let hmac = hmac.finalize().into_bytes();
    let hash = hex::encode_upper(hmac);


    let hash_url = format!(
        "https://tmdb.np.dl.playstation.net/tmdb2/{}_{}/{}.json",
        image_code, hash, image_code
    );

    // get image url from link
    let url = match ureq::get(hash_url.as_ref())
        .set("User-Agent", PS4_USER_AGENT)
        .call()
    {
        Ok(response) => {
            let temp: Value = response.into_json().expect("Failed to parse JSON");

            temp.get("icons")
                .context("Failed to parse JSON")?
                .get(0)
                .context("Failed to parse JSON")?
                .get("icon")
                .and_then(Value::as_str)
                .context("Failed to parse JSON")?
                .to_string()
        }
        Err(response) => bail!("Image request failed: {}", response),
    };

    image_downloader(url, PS4_USER_AGENT)
}


pub(crate) fn image_downloader(url: String, user_agent: &str) -> Result<Vec<u8>, anyhow::Error> {
    let response = ureq::get(&url)
        .set("User-Agent", user_agent)
        .call()?;
    
    let mut reader = response.into_reader();
    let mut buffer = Vec::new();
    reader.read_to_end(&mut buffer)?;
    
    Ok(buffer)
}

fn download_database(homebrew_database: &str, homebrew_token: &str) -> Result<(), anyhow::Error> {
    // Downloads the new database
    let database_response = ureq::get("https://api.pkg-zone.com/store.db")
        .set("User-Agent", &homebrew_token)
        .call()?;

    let mut file = File::create(&homebrew_database).polar_unwrap("Failed to create file!", true);
    std::io::copy(&mut database_response.into_reader(), &mut file)?;
    
    Ok(())   
}

pub(crate) fn homebrew_database_updater(homebrew_database: &str, homebrew_token: &str) -> Result<(), anyhow::Error> {
    use md5::{Digest, Md5};
    use chrono::{Timelike, Utc};
    use hex::encode;
    use std::path::Path;

    let minute: u32 = Utc::now().minute();
    //let minute: u32 = 58; // for debug
    
    if !Path::new(&homebrew_database).exists() {
        println_cyan!("Downloading database!");

        match download_database(homebrew_database, homebrew_token) {
            Ok(_) => println_cyan!("Saved Database In: \"{}\" Successfully!", &homebrew_database),
            Err(err) => bail!("Error downloading database! {}", err),
        }
        
        return Ok(())
    }
    
    
    if !(4..=57).contains(&minute) {
        
        let hash_response = ureq::get("https://api.pkg-zone.com/api.php?db_check_hash=true")
            .set("User-Agent", &homebrew_token)
            .call()?;

        let new_hash: String = {
            let body: Value = hash_response.into_json()?;

            body.get("hash")
                .and_then(Value::as_str)
                .context("Error while getting new_hash!")?
                .to_string()
        };
        
        let local_hash: String = match fs::read(&homebrew_database) {
            Ok(file) => encode(Md5::digest(file)),
            Err(err) => bail!("Homebrew Database not found: {}", err),
        };

        // Compares the current hash with the new hash
        if new_hash != local_hash {
            println_cyan!("MD5Hash: {local_hash} => {new_hash}");
            println_cyan!("Updating database!");

            match download_database(homebrew_database, homebrew_token) {
                Ok(_) => println_cyan!("Saved Database In: \"{}\" Successfully!", &homebrew_database),
                Err(err) => bail!("Error downloading database! {}", err),
            }
        }
    }
    Ok(())
}