use std::fs;
use std::path::Path;
use std::sync::{Arc, Mutex};
use anyhow::bail;
use fast_image_resize::images::Image;
use fast_image_resize::{FilterType, PixelType, ResizeAlg, ResizeOptions, Resizer, SrcCropping};
use load_image::export::imgref::ImgVec;
use ravif::{AlphaColorMode, ColorSpace, EncodedImage, Encoder, Img, RGBA8};
use rusqlite::Connection;
use crate::{networking, println_green};
use crate::macros::PolarResultUnwrap;
use crate::parsing::{GameType, Issue};

const RESIZE_OPTIONS: ResizeOptions = ResizeOptions {
    algorithm: ResizeAlg::Convolution(FilterType::Lanczos3),
    cropping: SrcCropping::None,
    mul_div_alpha: true,
};

fn convert_image(image_data: Vec<u8>) -> anyhow::Result<Vec<u8>> {
    
    let quality: f32 = 60f32;
    let alpha_quality = ((quality + 100.)/2.).min(quality + quality/4. + 2.);
    let speed: u8 = 1;
    let color_space = ColorSpace::YCbCr;
    let depth = Some(10);

    let img = load_rgba(image_data)?;

    let cropped_image = crop_image(img)?;

    let rgba_pixels: Vec<RGBA8> = cropped_image
        .chunks(4)
        .map(|chunk| RGBA8 {
            r: chunk[0],
            g: chunk[1],
            b: chunk[2],
            a: chunk[3],
        })
        .collect();

    let img = Img::new(rgba_pixels, 256,256);
    

    let enc = Encoder::new()
        .with_quality(quality)
        .with_depth(depth)
        .with_speed(speed)
        .with_alpha_quality(alpha_quality)
        .with_internal_color_space(color_space)
        .with_alpha_color_mode(AlphaColorMode::UnassociatedClean)
        .with_num_threads(None);

    let EncodedImage { avif_file, .. } = enc.encode_rgba(img.as_ref())?;
    
    Ok(avif_file)
}


fn load_rgba(data: Vec<u8>) -> anyhow::Result<ImgVec<RGBA8>> {
    use rgb::prelude::*;

    let img = load_image::load_data(&data)?.into_imgvec();

    let img = match img {
        load_image::export::imgref::ImgVecKind::RGB8(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.with_alpha(255)).collect()),
        load_image::export::imgref::ImgVecKind::RGBA8(img) => img,
        load_image::export::imgref::ImgVecKind::RGB16(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.map(|c| (c >> 8) as u8).with_alpha(255)).collect()),
        load_image::export::imgref::ImgVecKind::RGBA16(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.map(|c| (c >> 8) as u8)).collect()),
        load_image::export::imgref::ImgVecKind::GRAY8(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = g.0; RGBA8::new(c,c,c,255) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAY16(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = (g.0>>8) as u8; RGBA8::new(c,c,c,255) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAYA8(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = g.0; RGBA8::new(c,c,c,g.1) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAYA16(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = (g.0>>8) as u8; RGBA8::new(c,c,c,(g.1>>8) as u8) }).collect()),
    };
    
    Ok(img)
}


fn crop_image(image_data: ImgVec<RGBA8>) -> anyhow::Result<Vec<u8>> {

    let width: u32 = image_data.width() as u32;
    let height: u32 = image_data.height() as u32;

    let img_bytes: Vec<u8> = image_data.into_buf().iter().flat_map(|pixel| vec![pixel.r, pixel.g, pixel.b, pixel.a]).collect();

    let src_image = Image::from_vec_u8(
        width,
        height,
        img_bytes,
        PixelType::U8x4, // RGBA8
    )?;


    let mut cropped_image = Image::new(
        256,
        256,
        PixelType::U8x4, // RGBA8
    );

    let mut resizer = Resizer::new();
    resizer.resize(&src_image, &mut cropped_image, &RESIZE_OPTIONS)?;

    Ok(cropped_image.into_vec())
}


pub fn get_converted_image_data(issue: &mut Issue, images_path: &str, homebrew_db: Arc<Mutex<Connection>>, homebrew_token: &str) -> Result<(), anyhow::Error> {

    // get the images based on what the game type is
    match issue.issue_type {
        GameType::Game => {
            let path = format!("{}/game/{}.avif", images_path, issue.code);

            if Path::new(&path).exists() {
                issue.image = true;
                return Ok(());
            }

            // DOWNLOAD IMAGE ;3
            let data =  networking::get_game_image_data(&issue.code)?;
            let avif_image = convert_image(data)?;


            // maybe maybe maybe
            fs::write(path, avif_image).polar_unwrap("Error writing image!", true);

            issue.image = true;
            println_green!("Downloaded and saved image for {}", issue.code);

            Ok(())
        }
        GameType::Homebrew => {
            let path = format!("{}/homebrew/{}.avif", images_path, issue.title);

            if Path::new(&path).exists() {
                issue.image = true;
                return Ok(());
            }


           let homebrew_db =  homebrew_db.lock().polar_unwrap("Issue with mutex, aborting!", true);

            let mut stmt = homebrew_db
                .prepare("SELECT image FROM homebrews WHERE name = (?1) COLLATE NOCASE")?;

            // black magic
            let url = {
                let temp = stmt
                    .query_map([&issue.title], |row| Ok(row.get::<_, String>(0)))?
                    .next();

                match temp {
                    Some(result) => result??,
                    None => bail!("No image url found for {}", issue.title),
                }
            };

            // DOWNLOAD IMAGE ;3
            let data = networking::image_downloader(url, homebrew_token)?;
            let avif_image = convert_image(data)?;

            // maybe maybe maybe
            fs::write(path, avif_image).polar_unwrap("Error writing image!", true);

            issue.image = true;
            println_green!("Downloaded and saved image for {}", issue.title);

            Ok(())
        }
        _ => bail!("No image for the issue_type: {:?}", issue.issue_type),
    }
}