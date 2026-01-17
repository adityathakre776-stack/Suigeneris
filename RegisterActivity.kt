package com.emergency.trigger

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.emergency.trigger.api.ApiClient
import com.emergency.trigger.api.ApiResult
import com.emergency.trigger.api.SessionManager
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import kotlinx.coroutines.*

class RegisterActivity : AppCompatActivity() {
    
    private lateinit var etName: TextInputEditText
    private lateinit var etPhone: TextInputEditText
    private lateinit var etEmail: TextInputEditText
    private lateinit var etPassword: TextInputEditText
    private lateinit var etConfirmPassword: TextInputEditText
    private lateinit var btnRegister: MaterialButton
    private lateinit var progressBar: View
    
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)
        
        etName = findViewById(R.id.etName)
        etPhone = findViewById(R.id.etPhone)
        etEmail = findViewById(R.id.etEmail)
        etPassword = findViewById(R.id.etPassword)
        etConfirmPassword = findViewById(R.id.etConfirmPassword)
        btnRegister = findViewById(R.id.btnRegister)
        progressBar = findViewById(R.id.progressBar)
        
        btnRegister.setOnClickListener { register() }
        
        findViewById<View>(R.id.btnGoToLogin).setOnClickListener {
            finish()
        }
    }
    
    private fun register() {
        val name = etName.text.toString().trim()
        val phone = etPhone.text.toString().trim()
        val email = etEmail.text.toString().trim()
        val password = etPassword.text.toString()
        val confirmPassword = etConfirmPassword.text.toString()
        
        // Validation
        if (name.isEmpty()) {
            etName.error = "Name is required"
            return
        }
        
        if (phone.isEmpty()) {
            etPhone.error = "Phone is required"
            return
        }
        
        if (phone.length < 10) {
            etPhone.error = "Invalid phone number"
            return
        }
        
        if (email.isEmpty()) {
            etEmail.error = "Email is required for verification"
            return
        }
        
        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            etEmail.error = "Invalid email address"
            return
        }
        
        if (password.isEmpty()) {
            etPassword.error = "Password is required"
            return
        }
        
        if (password.length < 6) {
            etPassword.error = "Password must be at least 6 characters"
            return
        }
        
        if (password != confirmPassword) {
            etConfirmPassword.error = "Passwords do not match"
            return
        }
        
        setLoading(true)
        
        scope.launch {
            val result = ApiClient.register(name, phone, password, email)
            
            setLoading(false)
            
            when (result) {
                is ApiResult.Success -> {
                    val data = result.data
                    val token = data.optString("token")
                    val user = data.optJSONObject("user")
                    
                    if (token.isNotEmpty() && user != null) {
                        // Go to Email verification
                        val intent = Intent(this@RegisterActivity, OtpActivity::class.java).apply {
                            putExtra("email", email)
                            putExtra("token", token)
                            putExtra("user_id", user.optInt("id"))
                            putExtra("user_name", user.optString("name"))
                            putExtra("user_phone", phone)
                        }
                        startActivity(intent)
                        finish()
                    } else {
                        Toast.makeText(this@RegisterActivity, "Registration failed", Toast.LENGTH_SHORT).show()
                    }
                }
                is ApiResult.Error -> {
                    Toast.makeText(this@RegisterActivity, result.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }
    
    private fun setLoading(loading: Boolean) {
        progressBar.visibility = if (loading) View.VISIBLE else View.GONE
        btnRegister.isEnabled = !loading
    }
    
    override fun onDestroy() {
        super.onDestroy()
        scope.cancel()
    }
}
