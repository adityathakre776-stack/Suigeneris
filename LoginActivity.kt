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

class LoginActivity : AppCompatActivity() {
    
    private lateinit var etPhone: TextInputEditText
    private lateinit var etPassword: TextInputEditText
    private lateinit var btnLogin: MaterialButton
    private lateinit var progressBar: View
    
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Check if already logged in
        if (SessionManager.isLoggedIn(this)) {
            goToMain()
            return
        }
        
        setContentView(R.layout.activity_login)
        
        etPhone = findViewById(R.id.etPhone)
        etPassword = findViewById(R.id.etPassword)
        btnLogin = findViewById(R.id.btnLogin)
        progressBar = findViewById(R.id.progressBar)
        
        btnLogin.setOnClickListener { login() }
        
        findViewById<View>(R.id.btnGoToRegister).setOnClickListener {
            startActivity(Intent(this, RegisterActivity::class.java))
        }
    }
    
    private fun login() {
        val phone = etPhone.text.toString().trim()
        val password = etPassword.text.toString()
        
        if (phone.isEmpty()) {
            etPhone.error = "Phone is required"
            return
        }
        
        if (password.isEmpty()) {
            etPassword.error = "Password is required"
            return
        }
        
        setLoading(true)
        
        scope.launch {
            val result = ApiClient.login(phone, password)
            
            setLoading(false)
            
            when (result) {
                is ApiResult.Success -> {
                    val data = result.data
                    val token = data.optString("token")
                    val user = data.optJSONObject("user")
                    
                    if (token.isNotEmpty() && user != null) {
                        SessionManager.saveSession(
                            this@LoginActivity,
                            token,
                            user.optInt("id"),
                            user.optString("name"),
                            user.optString("phone"),
                            user.optString("email")
                        )
                        
                        Toast.makeText(this@LoginActivity, "Welcome back!", Toast.LENGTH_SHORT).show()
                        goToMain()
                    } else {
                        Toast.makeText(this@LoginActivity, "Login failed", Toast.LENGTH_SHORT).show()
                    }
                }
                is ApiResult.Error -> {
                    Toast.makeText(this@LoginActivity, result.message, Toast.LENGTH_LONG).show()
                }
            }
        }
    }
    
    private fun setLoading(loading: Boolean) {
        progressBar.visibility = if (loading) View.VISIBLE else View.GONE
        btnLogin.isEnabled = !loading
    }
    
    private fun goToMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
    
    override fun onDestroy() {
        super.onDestroy()
        scope.cancel()
    }
}
