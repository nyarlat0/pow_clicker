use ed25519_dalek::{Signer, SigningKey, VerifyingKey};
use serde::Serialize;
use sha2::{Digest, Sha256};
use wasm_bindgen::prelude::*;

#[derive(Serialize)]
pub struct PowResult {
    work_nonce: String,
    combined_num: String,
    hash: String,
}

#[wasm_bindgen]
pub fn generate_private_key() -> Result<String, JsValue> {
    let mut private_key = [0u8; 32];
    getrandom::fill(&mut private_key)
        .map_err(|err| JsValue::from_str(&format!("failed to generate random bytes: {err}")))?;

    Ok(hex::encode(private_key))
}

#[wasm_bindgen]
pub fn public_key_from_private_key(private_key_hex: &str) -> Result<String, JsValue> {
    let private_key_bytes = decode_32_bytes(private_key_hex, "private key")?;

    let signing_key = SigningKey::from_bytes(&private_key_bytes);
    let verifying_key: VerifyingKey = signing_key.verifying_key();

    Ok(hex::encode(verifying_key.to_bytes()))
}

#[wasm_bindgen]
pub fn is_valid_private_key(private_key_hex: &str) -> bool {
    decode_32_bytes(private_key_hex, "private key").is_ok()
}

fn decode_32_bytes(hex_string: &str, name: &str) -> Result<[u8; 32], JsValue> {
    let bytes =
        hex::decode(hex_string).map_err(|_| JsValue::from_str(&format!("Invalid {name} hex")))?;

    let bytes: [u8; 32] = bytes
        .try_into()
        .map_err(|_| JsValue::from_str(&format!("{name} must be 32 bytes")))?;

    Ok(bytes)
}

#[wasm_bindgen]
pub fn sign_message(nonce: &str, private_key_hex: &str, message: &str) -> Result<String, JsValue> {
    let private_key_bytes = decode_32_bytes(private_key_hex, "private key")?;
    let signing_key = SigningKey::from_bytes(&private_key_bytes);

    let signature = signing_key.sign((message.to_owned() + nonce).as_bytes());

    Ok(hex::encode(signature.to_bytes()))
}

#[wasm_bindgen]
pub fn solve_challenge(challenge_hex: &str, num_zeroes: usize) -> Result<JsValue, JsValue> {
    let challenge_bytes =
        hex::decode(challenge_hex).map_err(|_| JsValue::from_str("Invalid hex"))?;

    let required_prefix = "0".repeat(num_zeroes);

    let mut work_nonce: u64 = 0;

    loop {
        let mut hasher = Sha256::new();
        let nonce_bytes = work_nonce.to_be_bytes();

        hasher.update(&challenge_bytes);
        hasher.update(nonce_bytes);

        let hash = hasher.finalize();
        let hash_hex = hex::encode(hash);

        if hash_hex.starts_with(&required_prefix) {
            let nonce_bytes = work_nonce.to_be_bytes();
            let mut combined = Vec::with_capacity(challenge_bytes.len() + nonce_bytes.len());
            combined.extend_from_slice(&challenge_bytes);
            combined.extend_from_slice(&nonce_bytes);

            let result = PowResult {
                work_nonce: work_nonce.to_string(),
                combined_num: hex::encode(combined),
                hash: hash_hex,
            };

            return serde_wasm_bindgen::to_value(&result)
                .map_err(|err| JsValue::from_str(&err.to_string()));
        }

        work_nonce += 1;
    }
}
