package com.emergency.trigger

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.emergency.trigger.api.ApiClient
import com.emergency.trigger.api.ApiResult
import com.emergency.trigger.api.SessionManager
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import kotlinx.coroutines.*

class OtpActivity : AppCompatActivity() {
    
    private lateinit var tvEmail: TextView
    private lateinit var etOtp: TextInputEditText
    private lateinit var btnVerify: MaterialButton
    private lateinit var btnResend: TextView
    private lateinit var progressBar: View
    
    private var email: String = ""
    private var token: String = ""
    private var userId: Int = 0
    private var userName: String = ""
    private var userPhone: String = ""
    
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_otp)
        
        // Get data from intent
        email = intent.getStringExtra("email") ?: ""
        token = intent.getStringExtra("token") ?: ""
        userId = intent.getIntExtra("user_id", 0)
        userName = intent.getStringExtra("user_name") ?: ""
        userPhone = intent.getStringExtra("user_phone") ?: ""
        
        tvEmail = findViewById(R.id.tvPhoneNumber)
        etOtp = findViewById(R.id.etOtp)
        btnVerify = findViewById(R.id.btnVerify)
        btnResend = findViewById(R.id.btnResend)
        progressBar = findViewById(R.id.progressBar)
        
        // Mask email
        val maskedEmail = if (email.contains("@")) {
            val parts = email.split("@")
            val name = parts[0]
            val domain = parts[1]
            val masked = if (name.length > 3) {
                name.take(2) + "***" + name.takeLast(1)
            } else {
                name.first() + "***"
            }
            "$masked@$domain"
        } else email
        
        tvEmail.text = "Enter code sent to $maskedEmail"
        
        btnVerify.setOnClickListener { verifyOtp() }
        btnResend.setOnClickListener { resendOtp() }
        
        // Send OTP automatically
        sendOtp()
    }
    
    private fun sendOtp() {
        setLoading(true)
        scope.launch {
            val result = ApiClient.sendOtp(email)
            setLoading(false)
            
            when (result) {
                is ApiResult.Success -> {
                    Toast.makeText(this@OtpActivity, "Verification code sent to your email!", Toast.LENGTH_SHORT).show()
                }
                is ApiResult.Error -> {
                    Toast.makeText(this@OtpActivity, result.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }
    
    private fun resendOtp() {
        sendOtp()
    }
    
    private fun verifyOtp() {
        val otp = etOtp.text.toString().trim()
        
        if (otp.length != 6) {
            etOtp.error = "Enter 6-digit code"
            return
        }
        
        setLoading(true)
        scope.launch {
            val result = ApiClient.verifyOtp(email, otp)
            setLoading(false)
            
            when (result) {
                is ApiResult.Success -> {
                    // Save session and go to main
                    SessionManager.saveSession(
                        this@OtpActivity,
                        token,
                        userId,
                        userName,
                        userPhone,
                        email
                    )
                    
                    Toast.makeText(this@OtpActivity, "Email verified!", Toast.LENGTH_SHORT).show()
                    
                    startActivity(Intent(this@OtpActivity, MainActivity::class.java))
                    finishAffinity()
                }
                is ApiResult.Error -> {
                    Toast.makeText(this@OtpActivity, result.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }
    
    private fun setLoading(loading: Boolean) {
        progressBar.visibility = if (loading) View.VISIBLE else View.GONE
        btnVerify.isEnabled = !loading
        btnResend.isEnabled = !loading
    }
    
    override fun onDestroy() {
        super.onDestroy()
        scope.cancel()
    }
}
